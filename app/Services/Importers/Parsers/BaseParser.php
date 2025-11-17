<?php

namespace App\Services\Importers\Parsers;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

abstract class BaseParser
{
    abstract public function parse(string $fileName): array;

    abstract public function convert(array $from): Collection;

    abstract public function getTableName(): string;
    abstract public function getCsvDelimiter(): string;

    public function getReportCode(): string
    {
        return ""; // Placeholder
    }

    // abstract function getFileName(): string;
    public function import(string $filePath): int
    {
        Log::info("File to import: $filePath - ".$this->getDtoClass());
        $dataCsv = $this->parse($filePath);
        Log::info(' file parsed' . count($dataCsv));
        $dtoClassName = $this->getDtoClass();

        $final = $this->convert($dataCsv);

        Log::info('converted ' . count($final));

        logger('csv converted');
        if($final->isEmpty()) {
            return 0;
        }

        return $this->writeToDB($final, get_class($this));
    }

    /**
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function writeToDB($datas, $dtoClass, $report_code='')
    {

        $reflection = new ReflectionClass($dtoClass);

        $className = strtolower(str_replace('Manager', '', $reflection->getShortName()));
        $tableName = $this->getTableName();
        Log::info('start writedb -> '.$tableName);

        $fields = $this->describe();

        $names = array_column($fields, 'name');
        $fieldList = implode(',', $names);

        $vals = implode(',', array_fill(0, count($fields), '?'));

        $dateField = collect($fields)
            ->firstWhere('is_date', true)['name'] ?? null;

        if ($dateField) {
            $minDate = $datas->min($dateField);
            $maxDate = $datas->max($dateField);

            try {
                DB::connection('alternate')->beginTransaction();
                Log::info('start transaction');

                // Build the base delete query
                $deleteQuery = DB::connection('alternate')->table($tableName)->whereBetween($dateField, [$minDate->toDateString(), $maxDate->toDateString()]);

                // Add report_code condition if applicable
                if ($this->getReportCode() != '' || $report_code != '') {
                    $code = $report_code ?: $this->getReportCode();
                    $deleteQuery->where('report_code', $code);
                }

                $deleteQuery->delete();
                logger('after delete');

                // Prepare data for batch insert
                $rowsToInsert = [];
                $fieldNames = collect($fields)->pluck('name')->toArray();

                foreach ($datas as $row) {
                    $rowData = [];
                    foreach ($fieldNames as $propertyName) {
                        $value = $row->$propertyName;
                        $scale = ($propertyName === 'revenue') ? 2 : 4;
                        if($value!==null) {
                            if ($value instanceof CarbonImmutable) {
                                $value = $value->toDateString();
                            } elseif ($value instanceof BigDecimal) {
                                $value = $value->toScale($scale, RoundingMode::HALF_UP)->jsonSerialize();
                            } elseif (is_bool($value)) {
                                $value = (int)$value;
                            }

                            $rowData[$propertyName] = $value;
                        }
                    }
                    $rowsToInsert[] = $rowData;
                }

                // Perform batch insert with chunking
                $chunkSize = 1000;
                $totalRows = count($rowsToInsert);
                $insertedCount = 0;

                foreach (array_chunk($rowsToInsert, $chunkSize) as $chunk) {
                    DB::connection('alternate')->table($tableName)->insert($chunk);
                    $insertedCount += count($chunk);
                    Log::info("Inserted chunk of " . count($chunk) . " rows. Total inserted: {$insertedCount} of {$totalRows}");
                }

                DB::connection('alternate')->commit();
                Log::info('after commit');

                return 1;
            } catch (\Exception $ex) {
                Log::info($ex->getMessage());
                DB::connection('alternate')->rollBack();

                return 0;

            }
        } else {
            return 0;
        }
    }

    public function getDtoClass(): string
    {
        $parserClass = get_class($this);

        // Replace namespace and class name parts
        $dtoClass = str_replace(
            ['Parsers\\', 'Parser'],
            ['DTOs\\', 'DTO'],
            $parserClass
        );

        return $dtoClass;
    }

    public function describe(?string $class = null): array
    {
        // If no class is provided, use the calling class
        $class = $class ?? static::class;

        $reflection = new ReflectionClass($class);
        $defaultProperties = $reflection->getDefaultProperties();

        return collect($reflection->getProperties())
            ->filter(function (ReflectionProperty $property) {
                return ! $property->isStatic();
            })
            ->map(function ($property) use ($defaultProperties) {
                $propertyName = $property->getName();
                $value = isset($defaultProperties[$propertyName]) ? $defaultProperties[$propertyName] : null;

                $type = $property->getType();
                $typeName = 'mixed';
                $isNullable = false;

                if ($type instanceof ReflectionNamedType) {
                    $typeName = $type->getName();
                    $isNullable = $type->allowsNull();
                }

                // Check if the type is a date (CarbonInterface or a class that implements it)
                $isDate = false;
                if (class_exists($typeName)) {
                    $isDate = is_subclass_of($typeName, CarbonInterface::class);
                }

                return [
                    'name' => $propertyName,
                    'type' => $typeName,
                    'nullable' => $isNullable,
                    'value' => $value,
                    'is_date' => $isDate,
                ];
            })
            ->all();
    }
}
