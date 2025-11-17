<?php

namespace App\Services\Importers\Ssp;

use App\Services\Importers\Interfaces\CacheBuilderInterface;
use Carbon\CarbonImmutable;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use App\Services\Importers\Parsers\CsvParser;

class IxManager extends CsvParser implements CacheBuilderInterface
{
    public function __construct(
        public ?CarbonImmutable $date = null,
        public ?string $domain = null,
        public ?string $ad_unit = null,
        public ?string $inventory = null,
        public ?int $impressions = null,
        public ?BigDecimal $revenue = null,
    ) {}

    public static $mapping = [
        'date' => 'Data',
        'domain' => 'Dominio',
        'ad_unit' => 'AdUnit',
        'inventory' => 'Inventory',
        'impressions' => 'Impressions',
        'revenue' => 'Revenue',
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


            if(((int) str_replace([',', '.'], '', $assoc['impressions']))<=0) continue;

            $siteTag = $assoc['site_name'];
            $splitted = explode(' - ', $siteTag);

            $inventory = strtoupper(trim($assoc['creative_type'])) === 'VIDEO'
                ? 'VIDEO'
                : 'DISPLAY';

            // === AdUnit logic ===
            if ($inventory === 'VIDEO') {
                $adUnit = substr(trim($siteTag), 0, 100);
            } elseif (count($splitted) > 3) {
                $adUnit = substr(trim($splitted[3]), 0, 100);
            } else {
                $adUnit = substr(trim($siteTag), 0, 100);
            }

            $result->push(new self(
                date: CarbonImmutable::parse($assoc['day']),
                domain: trim($assoc['domain']),
                ad_unit: $adUnit,
                inventory: $inventory,
                impressions: (int) str_replace([',', '.'], '', $assoc['impressions']),
                revenue: BigDecimal::of(str_replace(',', '.', $assoc['publisher_payment']))
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
        return "publisher_payment";
    }

    public function getTableName(): string
    {
        return "ssp_ix";
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
        // TODO: Implement getDomainCache() method.
    }
}
