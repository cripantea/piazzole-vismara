<?php

namespace App\Services\Importers\Ssp;

use App\Services\Importers\Helpers\ImportersHelper;
use App\Services\Importers\Interfaces\CacheBuilderInterface;
use Carbon\CarbonImmutable;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use App\Services\Importers\Parsers\CsvParser;
use Illuminate\Support\Facades\DB;

class OutbrainhbManager extends CsvParser implements CacheBuilderInterface
{
    public function __construct(
        public ?CarbonImmutable $date = null,
        public ?string $domain = null,
        public ?string $ad_unit = null,
        public ?int $impressions = null,
        public ?BigDecimal $revenue = null,
    ) {}

    public static $mapping = [
        'date' => 'Data',
        'domain' => 'Dominio',
        'ad_unit' => 'AdUnit',
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
                date: CarbonImmutable::createFromFormat('m/d/Y', $assoc[$header[0]]),
                domain: $assoc[$header[1]],
                ad_unit: $assoc[$header[4]],
                impressions: (int) $assoc[$header[2]],
                revenue: BigDecimal::of(str_replace(',', '.', $assoc[$header[3]]))
            ));
        }

        return $result;
    }

    public function getDelimiter(): string
    {
        return "TagId";
    }

    public function getTableName(): string
    {
        return "ssp_outbrain_hb";
    }

    public function getCsvDelimiter(): string
    {

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
                    'OUTBRAIN_HB' AS ssp,
                    domain,
                    date,
                    'DISPLAY' AS inventory_calc,
                    'OPEN MARKET' AS iddeal_calc,
                    0 AS requests,
                    SUM(impressions) AS impressions,
                    0 AS clicks,
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
