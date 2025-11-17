<?php

namespace App\Services\Importers\Ssp;

use App\Services\Importers\Interfaces\CacheBuilderInterface;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Brick\Math\BigDecimal;
use Filament\Actions\Imports\Importer;
use Illuminate\Support\Collection;
use App\Services\Importers\Parsers\CsvParser;
use App\Services\Importers\Helpers\ImportersHelper;
use Illuminate\Support\Facades\DB;

class GoogledealsManager extends CsvParser implements CacheBuilderInterface
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

    public static $smartClipOrders=[
        "2893404181",
        "2876617427",
        "2855833855",
        "2845422927",
        "2847364709",
        "2789220477",
        "2776787926",
        "2793116043",
        "2606989434",
        "2775434309",
        "2838021290",
        "2824903191",
        "2714564562",
        "2691304230",
        "2882865039",
        "2946674356",
        "2946497741",
        "2950317837",
        "2614006498",
        "2614668876",
        "2690635862",
        "2913628739",
        "2919680345",
        "2929195384",
        "2955889195",
        "2722783127",
        "2879314005",
        "2624148862",
        "2935110874",
        "2935095839",
        "2925554126",
        "2617579676",
        "2682459505",
        "2722999092",
        "2732835981",
        "2733994005",
        "2744101361",
        "2744101664",
        "2747863600",
        "2748291555",
        "2749288546",
        "2749764646",
        "2750396350",
        "2754957030",
        "2768746721",
        "2769745155",
        "2776403936",
        "2778242064",
        "2811491445",
        "2811492450",
        "2813260002",
        "2816033994",
        "2819633962",
        "2824903338",
        "2824934628",
        "2855833855",
        "2864981176",
        "2865482631",
        "2876617427",
        "2893404181",
        "2967104602",
        "2967153114",
    ];

    public function convert(array $parsed): Collection
    {
        $header = $parsed['header'];
        $rows = $parsed['rows'];

        $firstOfFebruary = CarbonImmutable::parse('2022-01-01');
        $result = collect();
        foreach ($rows as $riga) {
            $assoc = array_combine($header, $riga);
            if (! $assoc) {
                continue;
            }

            $line=new self(
                date : CarbonImmutable::createFromFormat('d/m/y', $assoc['Data']),
                domain : ImportersHelper::getDomain($assoc['Unità pubblicitaria']),
                ad_unit : ImportersHelper::getAdunit($assoc['Unità pubblicitaria']),
                inventory : ImportersHelper::getInventory($assoc['Dimensioni della creatività (pubblicata)']),
                id_deal : trim($assoc['ID ordine']),
                deal_name : trim($assoc['Ordine']),
                impressions : (int) str_replace('.', '', $assoc['Impressioni totali']),
                clicks : (int) str_replace('.', '', $assoc['Clic totali']),
                revenue : BigDecimal::of(str_replace(',', '.', $assoc['Entrate CPC e CPM totali (€)']))
            );

            if (in_array($line->id_deal, self::$smartClipOrders, true) && $line->date>$firstOfFebruary) {
                $line->id_deal = '[SMARTCLIP]' . $line->id_deal;
                $line->deal_name = '[SMARTCLIP]' . $line->deal_name;
            }
            $result->push($line);

        }
        $grouped = $result->groupBy(fn($item) => $item->date->format('Y-m-d') . '|' . $item->domain . '|' . $item->ad_unit. '|' . $item->inventory. '|' . $item->id_deal)
                ->map(function ($items) {
                    $first = $items->first();

                    return new self(
                        date: $first->date,
                        domain: $first->domain,
                        ad_unit: $first->ad_unit,
                        inventory: $first->inventory,
                        id_deal: $first->id_deal,
                        deal_name: $first->deal_name,
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
    public function getEndDelimiter(): string
    {

        return "Totale,"; // Placeholder
    }

    public function getDelimiter(): string
    {

        return "Unità pubblicitaria"; // Placeholder
    }

    public function getTableName(): string
    {
        return "ssp_google_deals";
    }

    public function getCsvDelimiter(): string
    {
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
                    'ADX' AS ssp,
                    domain,
                    date,
                    inventory as inventory_calc,
                    id_deal AS iddeal_calc,
                    0 as requests,
                    sum(impressions) as impressions,
                    sum(clicks) as clicks,
                    sum(revenue) as revenue
                FROM " . $this->getTableName() . "
                WHERE date = '" . $current_date->toDateString() . "'
                GROUP BY domain, date, inventory_calc, iddeal_calc
            ) calc";

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
        $firstApril2021=CarbonImmutable::parse('2021-04-01');
        if (count($idDealList) > 0) {
            // Load deals from DB
            $dealsOnDb = DB::connection('alternate')->table('deal')
                ->whereIn('id', $idDealList)
                ->where('ssp', 'ADX')
                ->where('dn', '!=', 0)
                ->get();

            foreach ($dealsOnDb as $deal) {
                if (!is_null($deal->dn)) {
                    $itemsOnDeal = $domainQueryCollection->where('id_deal', $deal->id)->all();
                    foreach ($itemsOnDeal as &$d) {

                        if($d->date>=$firstApril2021->toDateString()) {
                            $valoreDn = BigDecimal::of($d->revenue)->multipliedBy('10')->multipliedBy(BigDecimal::of($deal->dn))->dividedBy('9', 2, RoundingMode::HALF_EVEN);
                            $d->revenue = BigDecimal::of($d->revenue)->minus($valoreDn);
                            $dnList[] = [
                                'date' => $d->date,
                                'domain' => $d->domain,
                                'id_deal' => $d->id_deal,
                                'inventory' => $d->inventory,
                                'ssp' => $d->ssp,
                                'value' => $valoreDn
                            ];
                        } else {
                            $valoreDn = BigDecimal::of($d->revenue)->multipliedBy(BigDecimal::of($deal->dn))->toScale(2, RoundingMode::HALF_EVEN);
                            $costo=BigDecimal::of($d->revenue)->multipliedBy('0.1')->toScale(2, RoundingMode::HALF_EVEN);
                            $d->revenue = BigDecimal::of($d->revenue)->minus($valoreDn)->minus($costo);
                            $dnList[] = [
                                'date' => $d->date,
                                'domain' => $d->domain,
                                'id_deal' => $d->id_deal,
                                'inventory' => $d->inventory,
                                'ssp' => $d->ssp,
                                'value' => $valoreDn
                            ];
                        }
                    }
                    unset($d);
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
