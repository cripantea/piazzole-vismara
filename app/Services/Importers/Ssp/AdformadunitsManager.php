<?php

namespace App\Services\Importers\Ssp;

use App\Services\Importers\Interfaces\CacheBuilderInterface;
use Carbon\CarbonImmutable;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use App\Services\Importers\Parsers\CsvParser;
use Illuminate\Support\Facades\DB;

class AdformadunitsManager extends CsvParser implements CacheBuilderInterface
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
                date:CarbonImmutable::createFromFormat('d/m/Y', $assoc['Date']),
                domain: strtolower($assoc['Publisher']),
                ad_unit: $assoc['Placement']."_".$assoc['Placement ID'],
                inventory: $assoc['Placement Type'],
                id_deal: $assoc['Deal ID'],
                deal_name: $assoc['Deal Name'],
                impressions: (int) $assoc['Paid Impressions'] ,
                clicks: (int) $assoc['Clicks'],
                revenue: BigDecimal::of(str_replace(',', '.', $assoc['Revenue']))

            ));

        }

        return $result;
    }

    public function getDelimiter(): string
    {
        return "Placement Type"; // Placeholder
    }

    public function getTableName(): string
    {
        return "ssp_adform";
    }

    public function getCsvDelimiter(): string
    {

        return "\t"; // Placeholder
    }
    public function getEndDelimiter(): string{
        return "TOTAL";
    }

    public function getDomainCache(?CarbonImmutable $current_date): array
    {
           $domainQueryString="
            SELECT
                 ssp,
                 domain,
                 date,
                 inventory_calc AS inventory,
                 iddeal_calc AS iddeal,
                 requests,
                 impressions,
                 clicks,
                 revenue
             FROM (
                 SELECT
                     'ADFORM' AS ssp,
                     domain,
                     date,
                     (SELECT
                             IF(inventory = 'In-Stream',
                                     'VIDEO',
                                     'DISPLAY')
                         ) as inventory_calc,
                         (SELECT
                             IF(lower(id_deal) = 'n/a',
                                     'OPEN MARKET',
                                     id_deal)
                         ) as iddeal_calc,
                         0 as requests,
                         sum(impressions) as impressions,
                         sum(clicks) as clicks,
                         sum(revenue) as revenue
                    FROM " . $this->getTableName() . "
                    WHERE date = '" . $current_date->toDateString() . "'
                GROUP BY SSP , date , domain, inventory_calc, idDeal_calc) calc";



        $domainQueryResult = DB::connection('alternate')->select($domainQueryString);

        $domainQueryCollection = collect($domainQueryResult);

        $idDealList = $domainQueryCollection
            ->where('id_deal', '!=', 'OPEN MARKET')
            ->pluck('id_deal')
            ->unique()
            ->values()
            ->all();

        $domainList = $domainQueryCollection->toArray();
        $dnList = [];

        if (count($idDealList) > 0) {
            $dealsOnDb = DB::connection('alternate')->table('deal')
                ->whereIn('id', $idDealList)
                ->where('ssp', 'ADFORM')
                ->get();
            foreach ($dealsOnDb as $deal) {
                if (!is_null($deal->dn)) {
                    foreach ($domainList as &$item) {
                        if ($item->id_deal == $deal->id) {
                            $valoreDn = BigDecimal::of($item->revenue)->multipliedBy(BigDecimal::of('0.9'))->multipliedBy(BigDecimal::of($deal->dn))->toScale(2, RoundingMode::HALF_EVEN);
                            $dnList[] = [
                                'date' => $item->date,
                                'domain' => $item->domain,
                                'id_deal' => $item->id_deal,
                                'inventory' => $item->inventory,
                                'ssp' => $item->ssp,
                                'value' => $valoreDn
                            ];
                            $item->revenue = BigDecimal::of($item->revenue)->minus($valoreDn);
                        } else {
                            $item->revenue = BigDecimal::of($item->revenue);
                        }
                    }
                    unset($item); // break reference
                } else {
                    $domainList = array_filter($domainList, fn($x) => $x->id_deal != $deal->id);
                }
            }
        }

        return [
            'domains' => $domainList,
            'deals' => array_filter($dnList, fn($x) => $x['value']->compareTo(BigDecimal::zero()) > 0),
        ];

    }
}
