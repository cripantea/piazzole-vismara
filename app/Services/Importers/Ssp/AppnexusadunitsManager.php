<?php

namespace App\Services\Importers\Ssp;

use App\Services\Importers\Interfaces\CacheBuilderInterface;
use Carbon\CarbonImmutable;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use App\Services\Importers\Parsers\CsvParser;
use Illuminate\Support\Facades\DB;
use Brick\Math\RoundingMode;

class AppnexusadunitsManager extends CsvParser implements CacheBuilderInterface
{
    public function __construct(
        public ?CarbonImmutable $date = null,
        public ?string $domain = null,
        public ?string $ad_unit = null,
        public ?string $inventory = null,
        public ?string $id_deal = null,
        public ?string $deal_name = null,
        public ?int $impressions = null,
        public ?int $clicks = null,
        public ?BigDecimal $revenue = null,
        public bool $is_curated=false,
    ) {}

    public static $mapping = [
        'date' => 'Data',
        'domain' => 'Dominio',
        'ad_unit' => 'AdUnit',
        'inventory' => 'Inventory',
        'id_deal' => 'IdDeal',
        'deal_name' => 'DealName',
        'impressions' => 'Impressioni',
        'clicks' => 'Clicks',
        'revenue' => 'Entrate',
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

            if(floatval($assoc['profit'])==0) continue;
            $line = new self(
                date: CarbonImmutable::parse($assoc['day']),
                domain: strtolower($assoc['publisher']),
                ad_unit:$assoc['placement'],
                inventory: $assoc['mediatype'],
                id_deal: $assoc['deal'],
                deal_name: $assoc['deal'],
                impressions: (int) $assoc['imps'],
                clicks: 0,
                revenue:BigDecimal::of(str_replace(',', '.', $assoc['profit']))
            );
            if($line->revenue == BigDecimal::zero() || $line->impressions == 0){
                continue;
            }
            $pos = strpos($line->domain, "(");
            if ($pos !== false) {
                $line->domain = trim(substr($line->domain, 0, $pos));
            }
            if($line->deal_name!="--"){
                $deal = DB::connection('alternate')->table('ssp_appnexus_curated_list')
                    ->where('deal_name', $line->deal_name)
                    ->orWhere('deal_name', '(CURATED)' . $line->deal_name)
                    ->first();

                if (!$deal) {
                    continue;
                }

                $line->is_curated = (bool) $deal->is_curated;

                // Just in case, prepend "(CURATED)" if curated
                if ($line->is_curated) {
                    $line->deal_name = '(CURATED)' . $line->deal_name;
                    $line->id_deal=$line->deal_name;
                }

            }
            $result->push($line);
        }

        return $result;
    }

    public function writeToDB($datas, $dtoClass, $report_code='')
    {
        $dateField = 'date';
        $minDate = $datas->min($dateField);
        $maxDate = $datas->max($dateField);
        try {
            DB::connection('alternate')->beginTransaction();
            logger('start transaction');

            // Delete existing data for the date range
            $paramsDelete = [
                'dataStart' => $minDate->toDateString(),
                'dataFine' => $maxDate->toDateString(),
            ];

            DB::connection('alternate')->table('ssp_appnexus')->where('date', '>=', $minDate)->where('date', '<=', $maxDate)->delete();
            DB::connection('alternate')->table('ssp_appnexus_curated')->where('date', '>=', $minDate)->where('date', '<=', $maxDate)->delete();

            logger('after delete');

            // Group rows by table
            $groupedData = $datas->groupBy(fn ($row) => $row->is_curated ? 'ssp_appnexus_curated' : 'ssp_appnexus');

            // Process each table group
            foreach ($groupedData as $table => $rows) {
                $rowsToInsert = [];
                foreach ($rows as $row) {
                    $rowsToInsert[] = [
                        'date' => $row->date->toDateString(),
                        'domain' => $row->domain,
                        'ad_unit' => $row->ad_unit,
                        'inventory' => $row->inventory,
                        'id_deal' => $row->id_deal,
                        'deal_name' => $row->deal_name,
                        'impressions' => $row->impressions,
                        'clicks' => $row->clicks,
                        'revenue' => $row->revenue->toScale(2, RoundingMode::HALF_UP)->jsonSerialize(),
                    ];
                }

                if (!empty($rowsToInsert)) {
                    $chunkSize = 1000;
                    foreach (array_chunk($rowsToInsert, $chunkSize) as $chunk) {
                        DB::connection('alternate')->table($table)->insert($chunk);
                    }
                    //DB::table($table)->insert($rowsToInsert);
                    logger("Inserted " . count($rowsToInsert) . " rows into {$table}.");
                }
            }

            DB::connection('alternate')->commit();
            logger('after commit');
            return 1;
        } catch (\Exception $ex) {
            logger($ex->getMessage());
            DB::connection('alternate')->rollBack();
            return 0;
        }

        return count($datas);

    }




    public function getDelimiter(): string
    {
        return "mediatype"; // Placeholder
    }

    public function getTableName(): string
    {
        return "ssp_appnexus_curated";
    }

    public function getCsvDelimiter(): string
    {
        return ","; // Placeholder
    }

    public function getDomainCache(?CarbonImmutable $current_date): array
    {
        // TODO: Implement getDomainCache() method.
    }
}
