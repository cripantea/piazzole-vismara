<?php

namespace App\Services\Importers\Ssp;

use App\Services\Importers\Interfaces\CacheBuilderInterface;
use Carbon\CarbonImmutable;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use App\Services\Importers\Parsers\CsvParser;
use App\Services\Importers\Helpers\ImportersHelper;
use Illuminate\Support\Facades\DB;

class TeadsManager extends CsvParser implements CacheBuilderInterface
{
    public function __construct(
        public ?CarbonImmutable $date = null,
        public ?string $domain = null,
        public ?string $ad_unit = null,
        public ?string $inventory = null,
        public ?int $clicks = null,
        public ?int $impressions = null,
        public ?BigDecimal $revenue = null,
    ) {}

    public static $mapping = [
        'date' => 'Data',
        'domain' => 'Dominio',
        'ad_unit' => 'AdUnit',
        'inventory' => 'Inventory',
        'clicks' => 'Clicks',
        'impressions' => 'Impressioni',
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

            $result->push(new self(
                date: CarbonImmutable::parse( $assoc['day']),
                domain: $assoc['website_domain'],
                ad_unit: $assoc['placement']."_".ImportersHelper::getInventory($assoc['format_creative_type']),
                inventory: ImportersHelper::getInventory($assoc['format_creative_type']),
                clicks: (int) $assoc['click'],
                impressions: (int) $assoc['publisher_billable_volume'],
                revenue: BigDecimal::of(str_replace(',', '.', $assoc['teads_billing_converted_eur'])),
            ));

        }
        $grouped = $result->groupBy(fn($item) => $item->date->format('Y-m-d') . '|' . $item->domain . '|' . $item->ad_unit. '|' . $item->inventory)
                ->map(function ($items) {
                    $first = $items->first();

                    return new self(
                        date: $first->date,
                        domain: $first->domain,
                        ad_unit: $first->ad_unit,
                        inventory: $first->inventory,
                        clicks: $items->sum(fn($i) => $i->clicks),
                        impressions: $items->sum(fn($i) => $i->impressions),
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

        return "teads_billing_converted_eur"; // Placeholder
    }

    public function getTableName(): string
    {
        return "ssp_teads";
    }

    public function getCsvDelimiter(): string
    {
        // --- MARKER: CUSTOM CSV DELIMITER LOGIC REQUIRED HERE ---
        // This method should return the CSV field delimiter (e.g., ",", "\t", ";").
        // Example: return "\t"; // For tab-separated values
        // Example: return ","; // For comma-separated values
        // --- END MARKER ---
        return ","; // Placeholder
    }

    public function getDomainCache(?CarbonImmutable $current_date): array
    {
        $domainQueryString = "
            SELECT
                ssp,
                domain,
                date,
                inventory_calc AS inventory,
                iddeal_calc AS id_deal,
                requests,
                impressions,
                clicks,
                revenue
            FROM (
                SELECT
                    'TEADS' AS ssp,
                    domain,
                    date,
                    inventory AS inventory_calc,
                    'OPEN MARKET' AS iddeal_calc,
                    0 AS requests,
                    SUM(impressions) AS impressions,
                    sum(clicks) AS clicks,
                    SUM(revenue) AS revenue
                FROM " . $this->getTableName() . "
                WHERE date = '" . $current_date->toDateString() . "'
                GROUP BY domain, date, inventory_calc, iddeal_calc
            ) calc";

        $domainQueryResult = DB::connection('alternate')->select($domainQueryString);

        $domainQueryCollection = collect($domainQueryResult);

        $domainList= $domainQueryCollection->toArray();

//        foreach ($domainList as $item) {
//            $a=(ImportersHelper::convertUsdToEur(BigDecimal::of($item->revenue), $current_date));
//            $item->revenue = $a;
//        }
        return [
            'domains' => $domainList,
            'deals' => [],
        ];
    }
}
