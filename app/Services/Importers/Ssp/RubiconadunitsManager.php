<?php

namespace App\Services\Importers\Ssp;

use App\Services\Importers\Interfaces\CacheBuilderInterface;
use Carbon\CarbonImmutable;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use App\Services\Importers\Parsers\CsvParser;

class RubiconadunitsManager extends CsvParser implements CacheBuilderInterface
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
                date: CarbonImmutable::parse( $assoc['Date']),
                domain: $this->GetDominio($assoc['Zone'], $assoc['Referring Domain']),
                ad_unit: $this->GetZone($assoc['Zone'], $assoc['Zone ID'], $assoc['Referring Domain']),
                inventory: $assoc['Ad Format'],
                id_deal: $assoc['Deal ID'],
                deal_name: $assoc['Deal'],
                impressions: (int) $assoc['Paid Impressions'],
                clicks: 0,
                revenue: BigDecimal::of($assoc['Publisher Gross Revenue'])
            ));
        }

        $maxDate=$result->max('date');
        //= minDate).GroupBy(x=> new { x.Data, x.Dominio, x.AdUnit, x.Inventory, x.IdDeal
        $grouped = $result
                ->where('date', '>=', $maxDate->subDays(2)->toDateString())
                ->groupBy(fn($item) => $item->date->format('Y-m-d') . '|' . $item->domain . '|' . $item->ad_unit. '|' . $item->inventory. '|' . $item->id_deal)
                ->map(function ($items) {
                    $first = $items->first();

                    return new self(
                        date: $first->date,
                        domain: $first->domain,
                        ad_unit: $first->ad_unit,
                        inventory: $first->inventory,
                        id_deal: $first->id_deal,
                        deal_name: $first->deal_name,
                        impressions: $items->sum(fn($i) => $i->impressions),
                        clicks: $items->sum(fn($i) => $i->clicks),
                        revenue: $items->reduce(
                            fn($carry, $item) => $carry->plus($item->revenue),
                            BigDecimal::of('0')
                        )
                    );
                })
                ->filter(fn($item) => $item->impressions > 0);
        return $grouped;

    }

    private function GetZone(string $zone, string $zoneID, string $referringDomain): string
    {
        if ($this->IsZoneRosOrRon($zone)) {
            return str_replace("www.", "", trim($referringDomain)) . "_" . $zone;
        }

        return $zone . "_" . $zoneID;
    }

    private function GetDominio(string $zone, string $referringDomain): string
    {
        $dominio = trim($zone);

        if ($this->IsZoneRosOrRon($zone)) {
            return str_replace("www.", "", trim($referringDomain));
        }

        try {
            $splitted = explode('_', strtolower($dominio));
            return $splitted[0];
        } catch (\Throwable $e) {
        }

        return $dominio;
    }

    private function IsZoneRosOrRon(string $zone): bool
    {
        $theZone = trim(strtolower($zone));

        $rosRonZones = [
            "ros",
            "ron",
            "ros video",
            "ron video",
            "ros display",
            "ron display"
        ];

        return in_array($theZone, $rosRonZones);
    }

    public function getDelimiter(): string
    {
        return "Referring Domain";
    }

    public function getTableName(): string
    {
        return "ssp_rubicon";
    }

    public function getCsvDelimiter(): string
    {
        return ",";
    }

    public function getDomainCache(?CarbonImmutable $current_date): array
    {
        // Not implemented for RubiconadunitsManager
        return [
            'domains' => [],
            'deals' => [],
        ];
    }
}
