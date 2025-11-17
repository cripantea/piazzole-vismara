<?php

namespace App\Services\Importers\Ssp;

use App\Models\SspAniview;
use App\Services\Importers\Helpers\ImportersHelper;
use App\Services\Importers\Interfaces\CacheBuilderInterface;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Importer\DTOs\AniviewDTO;
use App\Services\Importers\Interfaces\SspParserInterface;
use App\Services\Importers\Parsers\CsvParser;

class EvolutionManager extends CsvParser implements CacheBuilderInterface
{
    public function __construct(
        public ?CarbonImmutable $date=null,
        public ?string $domain=null,
        public ?string $ad_unit=null,
        public ?string  $inventory=null,
        public ?int $impressions=null,
        public ?int $clicks=null,
        public ?BigDecimal $revenue=null
    ){}


    public static $mapping = [
        'date' => 'Data',
        'domain' => 'Dominio',
        'impressions' => 'Impressioni',
        'inventory' => 'Inventory',
        'ad_unit' => 'AdUnit',
        'clicks' => 'Clicks',
        'revenue' => 'Entrate',
    ];

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

            if($assoc['Date']=='Total') continue;
            $revenue=BigDecimal::of($assoc['Publisher Revenue, $']);
            if($revenue->isZero() && (((int) $assoc['Clicks']) ==0) && (((int) $assoc['Impressions']) ==0)) continue;

            $inventory=str_contains(strtolower($assoc['Inventory']), 'display')?'DISPLAY':'VIDEO';

            $result->push(new self(
                date: CarbonImmutable::parse( $assoc['Date']),
                domain: $assoc['Domain / Bundle'],
                ad_unit: $assoc['Domain / Bundle']."_".$inventory,
                inventory: $inventory,
                impressions: (int) $assoc['Impressions'],
                clicks: 0, // non so perche. il dato c'è ma nella dash precedente non viene importato
                revenue: BigDecimal::of($assoc['Publisher Revenue, $'])
            ));
        }
        return $result;
    }

    // public function specialConditions(Collection $datiFile): Collection
    // {
    //     return $datiFile;
    // }

    public function getDelimiter(): string{
        return "Fill Rate";
    }

    public function getTableName(): string{
        return "ssp_evolution";
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
                    'E_VOLUTION' AS ssp,
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

        foreach ($domainList as $item) {
            $a=(ImportersHelper::convertUsdToEur(BigDecimal::of($item->revenue), $current_date));
            $item->revenue = $a;
        }
        return [
            'domains' => $domainList,
            'deals' => [],
        ];
    }
}
