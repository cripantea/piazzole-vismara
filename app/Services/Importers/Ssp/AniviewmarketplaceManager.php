<?php

namespace App\Services\Importers\Ssp;

use App\Services\Importers\Helpers\ImportersHelper;
use App\Services\Importers\Interfaces\CacheBuilderInterface;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Brick\Math\BigDecimal;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\Importers\Parsers\CsvParser;

class AniviewmarketplaceManager extends CsvParser implements CacheBuilderInterface
{
    public function __construct(
        public ?CarbonImmutable $date = null,
        public ?string $domain = null,
        public ?int $impressions = null,
        public ?BigDecimal $revenue = null,
    ) {}

    public static $mapping = [
        'date' => 'Data',
        'domain' => 'Dominio',
        'impressions' => 'Impressioni',
        'revenue' => 'Entrate',
    ];

    public function convert(array $parsed): Collection
    {
        $header = $parsed['header'];
        $rows = $parsed['rows'];

        $result = collect();
        foreach ($rows as $riga) {
            if(count($riga)!=count($header)) continue;
            try{
                $assoc = array_combine($header, $riga);
            } catch (Exception $ex){
                echo $riga;
                continue;
            }
            if (! $assoc) {
                continue;
            }
            $result->push(new self(
                date: CarbonImmutable::parse($assoc['Date']),
                domain: $assoc['Grouped Domain'],
                impressions: (int) $assoc['Impression'],
                revenue: BigDecimal::of(str_replace(',', '.', $assoc['Revenue']))
            ));
        }

        return $result;
    }

    public function getDelimiter(): string
    {
        return "Grouped Domain";
    }

    public function getTableName(): string
    {
        return "ssp_aniview_marketplace";
    }

    public function getCsvDelimiter(): string
    {
        return ",";
    }
    private const DOMAINS_WITH_COEFFICIENT_ONE = [
        "familydaysout.com",
        "adnkronos.com",
        "donnemagazine.it",
        "foodblog.it",
        "gossipetv.com",
        "ilcuoreinpentola.it",
        "ilgiornale.it",
        "iltempo.it",
        "investimentimagazine.it",
        "lanostratv.it",
        "liberoquotidiano.it",
        "motorimagazine.it",
        "notizie.it",
        "orizzontescuola.it",
        "tuobenessere.it",
        "vaielettrico.it"
    ];

    /**
     * Determines the revenue coefficient based on the domain and the date.
     *
     * @param string $domain The grouped domain name.
     * @param \DateTimeImmutable $currentDate The date of the report/message.
     * @return float The calculated coefficient (0.7, 0.8, 0.9, or 1.0).
     */
    private function getCoefficient(string $domain, CarbonImmutable $currentDate): BigDecimal
    {
        $coefficient = BigDecimal::of('0.7');
        $date20221001 = CarbonImmutable::parse('2022-10-01');
        $date20230412 = CarbonImmutable::parse('2023-04-12');
        if ($currentDate >= $date20230412) {
            $coefficient = BigDecimal::of('0.9');
            if (in_array($domain, self::DOMAINS_WITH_COEFFICIENT_ONE, true)) {
                $coefficient = BigDecimal::of('1.0');
            }
        } elseif ($currentDate >= $date20221001) {
            $coefficient = BigDecimal::of('0.8');
        }
        return $coefficient;
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
                    'ANIVIEW_MARKETPLACE' AS ssp,
                    domain,
                    date,
                    'VIDEO' AS inventory_calc,
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
            $a=(ImportersHelper::convertUsdToEur(BigDecimal::of($item->revenue)->multipliedBy($this->getCoefficient($item->domain, $current_date))->toScale(2, RoundingMode::HALF_EVEN), $current_date));
            $item->revenue = $a;
        }

        return [
            'domains' => $domainList,
            'deals' => [],
        ];
    }
}
