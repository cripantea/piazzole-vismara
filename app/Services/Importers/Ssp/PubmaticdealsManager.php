<?php

namespace App\Services\Importers\Ssp;

use App\Services\Importers\Interfaces\CacheBuilderInterface;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Brick\Math\BigDecimal;
use Carbon\CarbonPeriodImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\Importers\Parsers\CsvParser;
use App\Services\Importers\Parsers\ExcelParser;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Str;

class PubmaticdealsManager extends ExcelParser implements CacheBuilderInterface
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


    public function parse(string $filename): array
    {
        $output = Excel::toCollection(null, $filename)->first();
        if ($output->isEmpty()) {
            return [
                'header' => [],
                'rows' => [],
            ];
        }

        $startParsing = false;
        $startIndex=null;
        foreach ($output as $rowIndex => $row) {
            if ($rowIndex === 0 || empty($row)) {
                continue;
            }
            if (strtolower(trim($row[4]))==="ad format") {
                $header=$row;
                $startIndex=$rowIndex;
                break;
            }
        }

        $header = $output[$startIndex]->toArray();
        $rows = array_slice($output->toArray(), $startIndex + 1);

        return [
            'header' => $header,
            'rows' => $rows,
        ];

    }


    public function convert(array $parsed): Collection
    {

        $header = $parsed['header'];
        $rows = $parsed['rows'];

        $result=collect();
        foreach ($rows as $riga)
        {
            $assoc = array_combine($header, $riga);
            if (! $assoc) {
                continue;
            }
            if(strtolower($assoc['Date'])=='total') continue;
            $result->push(new self(
                date: CarbonImmutable::parse( $assoc['Date']),
                domain: strtolower($assoc['Domain']),
                ad_unit: strtolower($assoc['Domain']).'_'.strtolower($assoc['Ad Format']),
                inventory: Str::contains(strtolower($assoc['Ad Format']), 'video')?'VIDEO':'DISPLAY',
                id_deal: strtolower($assoc['Deal Source'])=='pubmatic'?null:$assoc['Deal ID'],
                deal_name: $assoc['Deal'],
                impressions: (int) $assoc['Paid Impressions'],
                clicks: 0,
                revenue: BigDecimal::of($assoc['Revenue(€)'])->toScale(2, RoundingMode::DOWN),
            ));
        }



        $withDeal = $result->filter(fn ($item) => !is_null($item->id_deal));
        $withoutDeal = $result->filter(fn ($item) => is_null($item->id_deal));
        $mappedWithoutDeal = $withoutDeal->map(function ($item) {
            return new PubmaticdomainsManager(
                date: $item->date,
                domain: $item->domain,
                ad_unit: $item->ad_unit,
                inventory: $item->inventory,
                impressions: $item->impressions,
                clicks: $item->clicks,
                revenue: $item->revenue,
                report_code: 'deals report'
            );
        });
        $grouped = $mappedWithoutDeal->groupBy(fn($item) => $item->date->format('Y-m-d') . '|' . $item->domain . '|' . $item->ad_unit. '|' . $item->inventory)
                ->map(function ($items) {
                    $first = $items->first();

                    return new PubmaticdomainsManager(
                        date: $first->date,
                        domain: $first->domain,
                        ad_unit: $first->ad_unit,
                        inventory: $first->inventory,
                        report_code: $first->report_code,
                        impressions: $items->sum(fn($i) => $i->impressions),
                        clicks: $items->sum(fn($i) => $i->clicks),
                        revenue: $items->reduce(
                            fn($carry, $item) => $carry->plus($item->revenue),
                            BigDecimal::of('0')
                        )
                    );
                });
                //->values();


        $a=new PubmaticdomainsManager();
        $a->writeToDB($grouped, PubmaticdomainsManager::class, 'deals report');




        return $withDeal;
    }

    public function getDelimiter(): string
    {
        return "";
    }

    public function getTableName(): string
    {
        return "ssp_pubmatic_deals";
    }

    public function getCsvDelimiter(): string
    {
        return "";
    }
    public function getReportCode(): string
    {
        return ""; // Placeholder
    }

    public function getDomainCache(?CarbonImmutable $current_date): array
    {
        // TODO: Implement getDomainCache() method.
    }
}
