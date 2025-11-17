<?php

namespace App\Services\Importers\Ssp;

use App\Models\SspAniview;
use App\Services\Importers\Interfaces\CacheBuilderInterface;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Importer\DTOs\AniviewDTO;
use App\Services\Importers\Helpers\ImportersHelper;
use App\Services\Importers\Interfaces\SspParserInterface;
use App\Services\Importers\Parsers\CsvParser;

class EbdaManager extends CsvParser implements CacheBuilderInterface
{
    public function __construct(
        public ?CarbonImmutable $date=null,
        public ?string $domain=null,
        public ?string $ad_unit=null,
        public ?string $partner=null,
        public ?string  $inventory=null,
        public ?int $impressions=null,
        public ?BigDecimal $revenue=null
    ){}

    public static $mapping = [
        'date' => 'Data',
        'domain' => 'Dominio',
        'ad_unit' => 'AdUnit',
        'partner' => 'Partner',
        'inventory' => 'Inventory',
        'impressions' => 'Impressioni',
        'revenue' => 'Entrate',
    ];

    public function convert(array $parsed): Collection
    {
        $header = $parsed['header'];
        $rows = $parsed['rows'];

        $result=collect();
        foreach ($rows as $riga)
        {
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
            //$inventory=str_contains(strtolower($assoc['Inventory']), 'display')?'DISPLAY':'VIDEO';

            $result->push(new self(
                date: CarbonImmutable::createFromFormat('d/m/y', $assoc['Data']),
                domain: ImportersHelper::getDomain(strtolower($assoc['Unità pubblicitaria'])),
                ad_unit: ImportersHelper::getAdunit(strtolower($assoc['Unità pubblicitaria'])),
                partner: strtolower($assoc['Partner di rendimento']),
                inventory: ImportersHelper::getInventory(strtolower($assoc['Dimensioni della creatività (pubblicata)'])),
                impressions: (int)  str_replace('.', '', $assoc['Impressioni totali']),
                revenue: BigDecimal::of(str_replace(',', '.', str_replace('.', '', $assoc['Entrate totali (€)'])))
            ));
        }


        $grouped = $result->groupBy(fn($item) => $item->date->format('Y-m-d') . '|' . $item->domain . '|' . $item->ad_unit. '|' . $item->partner. '|' . $item->inventory)
                ->map(function ($items) {
                    $first = $items->first();

                    return new self(
                        date: $first->date,
                        domain: $first->domain,
                        ad_unit: $first->ad_unit,
                        partner: $first->partner,
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


    public function getDelimiter(): string{
        return "Data,Partner";
    }

    public function getTableName(): string{
        return "ssp_ebda";
    }
    public function getCsvDelimiter(): string{
        return ",";
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
                     'EBDA' AS ssp,
                     domain,
                     date,
                     IF(inventory LIKE '%video%' OR inventory LIKE '%audio%', 'VIDEO', 'DISPLAY') as inventory_calc,
                     'OPEN MARKET' as iddeal_calc ,
                     0 as requests,
                     sum(impressions) as impressions,
                     0 as clicks,
                     sum(revenue) as revenue
                FROM " . $this->getTableName() . "
                WHERE date = '" . $current_date->toDateString() . "'
                GROUP BY domain, date, inventory_calc, iddeal_calc
            ) calc";

        $domainQueryResult = DB::connection('alternate')->select($domainQueryString);

        $domainQueryCollection = collect($domainQueryResult);

        $domainList = $domainQueryCollection->toArray();
        foreach ($domainList as $item) {
            $a=BigDecimal::of($item->revenue);
            $item->revenue = $a;
        }


        return [
            'domains' => $domainList,
            'deals' => [],
        ];
    }
}
