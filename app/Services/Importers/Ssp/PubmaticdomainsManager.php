<?php

namespace App\Services\Importers\Ssp;

use Carbon\CarbonImmutable;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use App\Services\Importers\Parsers\CsvParser;
use App\Services\Importers\Helpers\ImportersHelper;
use App\Services\Importers\Interfaces\CacheBuilderInterface;

class PubmaticdomainsManager extends CsvParser implements CacheBuilderInterface
{
    public function __construct(
        public ?CarbonImmutable $date = null,
        public ?string $domain = null,
        public ?string $ad_unit = null,
        public ?string $inventory = null,
        public ?int $impressions = null,
        public ?int $clicks = null,
        public ?BigDecimal $revenue = null,
        public ?string $report_code = null,
    ) {}

    public static $mapping = [
        'date' => 'Data',
        'domain' => 'Dominio',
        'ad_unit' => 'AdUnit',
        'inventory' => 'Inventory',
        'impressions' => 'Impressioni',
        'clicks' => 'Clicks',
        'revenue' => 'Entrate',
        'report_code' => 'Source',
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
            if(((int) $assoc['Paid Impressions'])<=0) continue;
            if(strtolower($assoc['Channel'])!="open exchange") continue;

            $result->push(new self(
                date: CarbonImmutable::parse($assoc['Date']),
                domain: ImportersHelper::getUrl($assoc['Site']),
                ad_unit: $assoc['Ad Tag'],
                inventory: strtoupper($assoc['Ad Format']),
                impressions: (int) $assoc['Paid Impressions'],
                clicks: (int) $assoc['Clicks'],
                revenue: BigDecimal::of(str_replace(',', '.', $assoc['Revenue(€)']))->toScale(2, \Brick\Math\RoundingMode::DOWN),
                report_code: 'domains report',
            ));
        }
        $grouped = $result->groupBy(fn($item) => $item->date->format('Y-m-d') . '|' . $item->domain . '|' . $item->ad_unit. '|' . $item->inventory)
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
        return $grouped;
    }

    public function getDelimiter(): string
    {
        return "Ad Format"; // Placeholder
    }

    public function getTableName(): string
    {
        return "ssp_pubmatic";
    }

    public function getCsvDelimiter(): string
    {
        return ","; // Placeholder
    }

    public function getReportCode(): string
    {
        return "domains report"; // Placeholder
    }

    // Dummy implementation con errore intenzionale
    public function getDomainCache(?\Carbon\CarbonImmutable $current_date): array
    {
        $errore = 1/0; // errore intenzionale
        return [
            'domains' => [],
            'deals' => [],
        ];
    }


}
