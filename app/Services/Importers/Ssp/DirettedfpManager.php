<?php

namespace App\Services\Importers\Ssp;

use App\Services\Importers\Interfaces\CacheBuilderInterface;
use Carbon\CarbonImmutable;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use App\Services\Importers\Parsers\CsvParser;
use App\Services\Importers\Helpers\ImportersHelper;
use Illuminate\Support\Str;
class DirettedfpManager extends CsvParser implements CacheBuilderInterface
{
    public function __construct(
        public ?CarbonImmutable $date = null,
        public ?string $domain = null,
        public ?string $ad_unit = null,
        public ?string $inventory = null,
        public ?string $id_ordine = null,
        public ?string $ordine = null,
        public ?int $impressions = null,
        public ?int $clicks = null,
        public ?BigDecimal $revenue = null,
    ) {}

    public static $mapping = [
        'date' => 'Data',
        'domain' => 'Dominio',
        'ad_unit' => 'AdUnit',
        'inventory' => 'Inventory',
        'id_ordine' => 'IdOrdine',
        'ordine' => 'Ordine',
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
            $a=count($header);
            $b=count($riga);
            if(($a != $b) || strtolower($riga[0])=='totale') {
                continue;
            }
            $assoc = array_combine($header, $riga);
            if (! $assoc) {
                continue;
            }

            if($assoc['Data']=='Totale') continue;

            $result->push(new self(

                date:CarbonImmutable::createFromFormat('d/m/y', $assoc['Data']) ,
                domain: ImportersHelper::getDomain(strtolower($assoc['Unità pubblicitaria'])),
                ad_unit: ImportersHelper::getAdunit(strtolower($assoc['Unità pubblicitaria'])),
                inventory: Str::startsWith(strtolower(trim($assoc['Dimensioni della creatività (pubblicata)'])), 'video') ? 'VIDEO' : 'DISPLAY',
                id_ordine: trim($assoc['ID ordine']),
                ordine: trim($assoc['Ordine']),
                impressions: (int) str_replace(".", "", $assoc['Impressioni totali'])??0,
                clicks: (int) str_replace(".", "", $assoc['Clic totali'])??0,
                revenue: BigDecimal::of(str_replace(',', '.', str_replace('.', '', $assoc['Entrate CPC e CPM totali (€)']))),


            ));
        }
        $grouped = $result->groupBy(fn($item) => $item->date->format('Y-m-d') . '|' . $item->domain . '|' . $item->ad_unit. '|' . $item->inventory. '|' . $item->id_ordine)
                ->map(function ($items) {
                    $first = $items->first();

                    return new self(
                        date: $first->date,
                        domain: $first->domain,
                        ad_unit: $first->ad_unit,
                        inventory: $first->inventory,
                        id_ordine: $first->id_ordine,
                        ordine: $first->ordine,
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
        return "Dimensioni della creatività (pubblicata)"; // Placeholder
    }

    public function getTableName(): string
    {
        return "ssp_dirette_dfp";
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
