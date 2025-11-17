<?php

namespace App\Services\Importers\Ssp;

use App\Services\Importers\Interfaces\CacheBuilderInterface;
use App\Services\Importers\Interfaces\SspDownloadInterface;
use Carbon\CarbonImmutable;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTime;
use DateTimeZone;
use Google\AdsApi\AdManager\AdManagerSessionBuilder;
use Google\AdsApi\AdManager\Util\v202502\AdManagerDateTimes;
use Google\AdsApi\AdManager\Util\v202502\ReportDownloader;
use Google\AdsApi\AdManager\Util\v202502\StatementBuilder;
use Google\AdsApi\AdManager\v202502\Column;
use Google\AdsApi\AdManager\v202502\Dimension;
use Google\AdsApi\AdManager\v202502\ExportFormat;
use Google\AdsApi\AdManager\v202502\ReportJob;
use Google\AdsApi\AdManager\v202502\ReportQuery;
use Google\AdsApi\AdManager\v202502\ServiceFactory;
use Google\AdsApi\Common\OAuth2TokenBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Services\Importers\Parsers\CsvParser;
use Illuminate\Support\Facades\Storage;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

class ReportvtrsManager extends CsvParser
{
    public function __construct(
        public ?CarbonImmutable $date = null,
        public ?string $ad_unit = null,
        public ?string $device = null,
        public ?BigDecimal $vtr = null,
    ) {}

    public static $mapping = [
        'date' => 'Data',
        'ad_unit' => 'AdUnit',
        'device' => 'CategoriaDispositivo',
        'vtr' => 'VTR',
    ];

    public function convert(array $parsed): Collection
    {
        $header = $parsed['header'];
        $rows = $parsed['rows'];

        $result = collect();
        foreach ($rows as $riga) {
            $assoc = array_combine($header, $riga);
            if (! $assoc) {
                continue;
            }
            $adunitParts = explode('»', $assoc['Ad unit (all levels)']);
            $line=new self(
                date: CarbonImmutable::parse($assoc['Date']) ,
                ad_unit: $this->mapAdunitToOldName($assoc['Ad unit (all levels)']),
                device: $assoc['Device category'],
                vtr:  BigDecimal::of($assoc['Completion rate'])->multipliedBy(100),
            );

            $result->push($line);

        }
        return $result;
//        $grouped = $result->groupBy(fn($item) => $item->date->format('Y-m-d') . '|' . $item->ad_unit . '|' . $item->device)
//            ->map(function ($items) {
//                $first = $items->first();
//                $totalVtr = $items->reduce(
//                    fn($carry, $item) => $carry->plus(
//                        $item->vtr instanceof BigDecimal ? $item->vtr : BigDecimal::of((string) $item->vtr)
//                    ),
//                    BigDecimal::of('0')
//                );
//
//                $count = $items->count();
//
//                $averageVtr = $count > 0
//                    ? $totalVtr->dividedBy($count, 4, RoundingMode::HALF_UP)
//                    : BigDecimal::of('0');
//
//                return new self(
//                    date: $first->date,
//                    ad_unit: $first->ad_unit,
//                    device: $first->device,
//                    vtr: $averageVtr
//                );
//            });
//        return $grouped;


    }


//    public function writeToDB($datas, $dtoClass, $report_code='')
//    {
//
//        $reflection = new ReflectionClass($dtoClass);
//
//        $className = strtolower(str_replace('Manager', '', $reflection->getShortName()));
//        $tableName = $this->getTableName();
//        logger('start writedb -> '.$tableName);
//
//        $fields = $this->describe();
//
//        $names = array_column($fields, 'name');
//        $fieldList = implode(',', $names);
//
//        $vals = implode(',', array_fill(0, count($fields), '?'));
//
//        $dateField = collect($fields)
//            ->firstWhere('is_date', true)['name'] ?? null;
//
//        if ($dateField) {
//            $minIntervalDate = $datas->min($dateField);
//            $maxIntervalDate = $datas->max($dateField);
//
//            $currentDate = $minIntervalDate;
//
//            while ($currentDate <= $maxIntervalDate) {
//                $minDate=$currentDate;
//                $maxDate=$currentDate;
//
//                try {
//                    DB::connection('alternate')->beginTransaction();
//                    logger('start transaction');
//
//                    // Build the base delete query
//                    $deleteQuery = DB::connection('alternate')->table($tableName)->whereBetween($dateField, [$minDate->toDateString(), $maxDate->toDateString()]);
//
//                    // Add report_code condition if applicable
//                    if ($this->getReportCode() != '' || $report_code != '') {
//                        $code = $report_code ?: $this->getReportCode();
//                        $deleteQuery->where('report_code', $code);
//                    }
//
//                    $deleteQuery->delete();
//                    logger('after delete');
//
//                    // Prepare data for batch insert
//                    $rowsToInsert = [];
//                    $fieldNames = collect($fields)->pluck('name')->toArray();
//                    $dailyData = $datas->where(function ($item) use ($currentDate) {
//                        return $item->date->toDateString() === $currentDate->toDateString();
//                    });
//
//                    foreach ($dailyData as $row) {
//                        $rowData = [];
//                        foreach ($fieldNames as $propertyName) {
//                            $value = $row->$propertyName;
//                            $scale = ($propertyName == 'revenue') ? 2 : 4;
//
//                            if ($value instanceof CarbonImmutable) {
//                                $value = $value->toDateString();
//                            } elseif ($value instanceof BigDecimal) {
//                                $value = $value->toScale($scale, RoundingMode::HALF_UP)->jsonSerialize();
//                            } elseif (is_bool($value)) {
//                                $value = (int)$value;
//                            }
//
//                            $rowData[$propertyName] = $value;
//                        }
//                        $rowsToInsert[] = $rowData;
//                    }
//
//                    // Perform batch insert with chunking
//                    $chunkSize = 1000;
//                    $totalRows = count($rowsToInsert);
//                    $insertedCount = 0;
//
//                    foreach (array_chunk($rowsToInsert, $chunkSize) as $chunk) {
//                        DB::connection('alternate')->table($tableName)->insert($chunk);
//                        $insertedCount += count($chunk);
//                        logger("Inserted chunk of " . count($chunk) . " rows. Total inserted: {$insertedCount} of {$totalRows}");
//                    }
//
//                    DB::connection('alternate')->commit();
//                    logger('after commit');
//
//                    //return 1;
//                } catch (\Exception $ex) {
//                    logger($ex->getMessage());
//                    DB::connection('alternate')->rollBack();
//
//                    return 0;
//
//                }
//
//
//                $currentDate = $currentDate->addDay();
//            }
//            return 1;
//        } else {
//            return 0;
//        }
//    }

    private function mapAdunitToOldName(string $s): string
    {
        // Rimuove i numeri tra parentesi.
        $s = preg_replace('/\([0-9]+\)/', '', $s);

        // Costruisce la vecchia nomenclatura.
        $s = "5966054 » " . $s;
        $s = str_replace(" ", "", $s);
        $s = str_replace("»", " » ", $s);
        $s = trim($s);

        return $s;
    }
    public function getDelimiter(): string
    {

        return "Device category"; // Placeholder
    }

    public function getEndDelimiter(): string
    {
        return "Total"; // Placeholder
    }

    public function getTableName(): string
    {
        return "ssp_vtr_report";
    }

    public function getCsvDelimiter(): string
    {
        return ","; // Placeholder
    }

    public static function downloadFromAPI(?string $fromDate, ?string $toDate, ?string $fileName)
    {

        $existingReportId = 6283407177;

        if ($fromDate == null || $toDate == null) {
            $toDate = CarbonImmutable::now()->toDateString();
            $fromDate = CarbonImmutable::now()->subDays(7)->toDateString();
        }
        // Generate a refreshable OAuth2 credential for authentication.
        $oAuth2Credential = (new OAuth2TokenBuilder())
            ->fromFile(storage_path('app/adsapi_php.ini'))
            ->build();

        // Construct an API session configured from an `adsapi_php.ini` file
        // and the OAuth2 credentials above.
        $session = (new AdManagerSessionBuilder())
            ->fromFile(storage_path('app/adsapi_php.ini'))
            ->withOAuth2Credential($oAuth2Credential)
            ->build();

        $serviceFactory=new ServiceFactory();

        $reportService = $serviceFactory->createReportService($session);

        $reportJob = new ReportJob();
        $reportJob->setId($existingReportId);


        $reportJob = $reportService->runReportJob($reportJob);
        $reportJobId = $reportJob->getId();

        $reportDownloader = new ReportDownloader(
            $reportService,
            $reportJob->getId()
        );

        if ($reportDownloader->waitForReportToFinish()) {
            $stream = $reportDownloader->downloadReport(ExportFormat::CSV_DUMP, null);
        } else {
            print "Report failed.\n";
        }

        try {
            $reportContentGz = $stream->getContents();
            $reportContents = gzdecode($reportContentGz);


            $innerNewFilename = "GoogleVTRReport_{$fromDate}_{$toDate}_".CarbonImmutable::now()->toDateString().".csv";
            $innerPath = 'ssp/' . $innerNewFilename;

            // Salva il contenuto estratto sul disco Laravel 'local'
            Storage::disk('local')->put($innerPath, $reportContents);

        } catch (\Exception $ex) {
            throw ($ex);
        }

        return;
    }

}
