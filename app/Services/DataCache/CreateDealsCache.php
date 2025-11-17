<?php

namespace App\Services\DataCache;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateDealsCache
{

    public const QUERY="
        insert into deal (id, ssp, nome) select id, ssp, nome from (
            SELECT DISTINCT (id_deal) AS id, 'ADFORM' AS ssp, deal_name AS nome FROM ssp_adform WHERE date >= wDateFrom AND date <= wDateTo AND id_deal not in('n/a', '--', '0')
            union ALL
            SELECT DISTINCT (id_deal) AS id, 'APPNEXUS' AS ssp, deal_name AS nome FROM ssp_appnexus WHERE date >= wDateFrom AND date <= wDateTo AND id_deal not in('n/a', '--', '0')
            union ALL
            SELECT DISTINCT (id_deal) AS id, 'APPNEXUS_CURATED' AS ssp, deal_name AS nome FROM ssp_appnexus_curated WHERE date >= wDateFrom AND date <= wDateTo AND id_deal not in('n/a', '--', '0')
            union ALL
            SELECT DISTINCT (id_deal) AS id, 'AMAZON' AS ssp, deal_name AS nome FROM ssp_amazon WHERE date >= wDateFrom AND date <= wDateTo AND id_deal not in('n/a', '--', '0')
            union ALL
            SELECT DISTINCT (id_deal) AS id, 'RUBICON' AS ssp, deal_name AS nome FROM ssp_rubicon WHERE date >= wDateFrom AND date <= wDateTo AND id_deal not in('n/a', '--', '0')
            union ALL
            SELECT DISTINCT (id_deal) AS id, 'SMART' AS ssp, deal_name AS nome FROM ssp_smart WHERE date >= wDateFrom AND date <= wDateTo AND id_deal not in('n/a', '--', '0')
            UNION ALL
            SELECT DISTINCT(id_deal) as id, 'ADX' as ssp,  deal_name as nome FROM ssp_google_deals WHERE date >= wDateFrom AND date <= wDateTo AND id_deal not in('n/a', '--', '0')
            UNION ALL
            SELECT DISTINCT(id_deal) as id, 'PUBMATIC_DEALS' as ssp,  deal_name as nome FROM ssp_pubmatic_deals WHERE date >= wDateFrom AND date <= wDateTo AND id_deal not in('n/a', '--', '0')
        ) c
        on duplicate Key update deal.id=c.id;";

    public function __construct()
    {

    }

    public function handle(?string $dateFrom, ?string $dateTo): int
    {

        if (empty(trim($dateFrom)))
        {
            echo("Error: 'dateFrom' cannot be null or empty.");
            return 1;
        }

        if (empty(trim($dateTo)))
        {
            echo("Error: 'dateTo' cannot be null or empty.");
            return 1;
        }


        try {
            $carbonFrom = CarbonImmutable::createFromFormat('Y-m-d', $dateFrom);
            $carbonTo = CarbonImmutable::createFromFormat('Y-m-d', $dateTo);

        } catch (\Exception $e) {
            echo "Error: The provided date format is invalid. " . $e->getMessage();
            return 1;
        }

        // 3. Optional: Add a logical check (e.g., end date is not before start date)
        if ($dateFrom > $dateTo)
        {
            echo("Error: 'dateFrom' cannot be after 'dateTo'.");
            return 3;
        }

        $myQuery = str_replace(array('wDateFrom', 'wDateTo'), array("'$dateFrom'", "'$dateTo'"), self::QUERY);

        echo $myQuery;
        try{
            DB::connection('alternate')->query($myQuery);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
        echo "Deals cache created successfully!";
        return 0;
    }
}
