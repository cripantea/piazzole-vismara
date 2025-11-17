<?php


namespace App\Services\Importers\Helpers;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTime;
use Exception;
use Illuminate\Support\Facades\DB;
class ImportersHelper
{
    private static array $rateCache = [];
    public static function getDomain(string $s): string
    {
        $splitted = explode('»', $s);
        if (preg_match('/(.+)(\(\d+\))/', trim($splitted[0]), $matches)) {
            return trim($matches[1]);
        }
        return trim($splitted[0]); // Fallback
    }

    public static function getAdunit(string $s): string
    {
        $splitted = explode('»', $s);

        if (count($splitted) === 1) {
            return self::getDomain($s);
        }

        if (preg_match('/(.+)(\(\d+\))/', trim($splitted[1]), $matches)) {
            return trim($matches[1]);
        }

        return trim($splitted[1]); // Fallback
    }

    public static function getInventory(string $s): string
    {
        $sLower = strtolower($s);

        if (str_contains($sLower, 'video') || str_contains($sLower, 'audio')) {
            return 'VIDEO';
        }

        return 'DISPLAY';
    }
    public static function getUrl(string $s): string
    {
        $sLower = strtolower(trim($s));

        // Regex match (e.g., www.domain.com, domain.net, etc.)
        if (preg_match('/^(www)?[0-9a-zA-Z]+\.[a-zA-Z]{2,4}/', $sLower, $matches)) {
            if (!empty($matches[0])) {
                return $matches[0];
            }
        }

        // Criteo
        if (str_contains($sLower, 'comingsoon')) {
            return 'comingsoon.it';
        }
        if (str_contains($sLower, 'machenesanno')) {
            return 'machenesanno.it';
        }
        if (str_contains($sLower, 'trovacasa')) {
            return 'trovacasa.net';
        }

        // Pubmatic
        if (str_contains($sLower, 'if - hb')) {
            return 'informazionefiscale.it';
        }
        if (str_contains($sLower, 'money')) {
            return 'money.it';
        }

        // Default: return original trimmed string
        return trim($s);
    }


    public static function getEcpms(string $table, $dates)
    {
        $todayStartMonth = CarbonImmutable::now()->startOfMonth();
        $previousMonth   = $todayStartMonth->subMonth();

        // 1. Check if current month exists
        $count = DB::connection('alternate')->table($table)->where('data', $todayStartMonth->toDateString())->count();

        if ($count == 0) {
            $value = DB::connection('alternate')->table($table)->where('data', $previousMonth->toDateString())->value('valore');
            DB::connection('alternate')->table($table)->insert([
                'data'   => $todayStartMonth->toDateString(),
                'valore' => $value,
            ]);
        }

        // 2. Reduce all input dates to "first day of month" distinct values
        $ecpmDates = collect($dates)
            ->map(fn($d) => CarbonImmutable::parse($d)->startOfMonth()->toDateString())
            ->unique();

        // 3. Query the table
        return DB::connection('alternate')->table($table)
            ->whereIn('data', $ecpmDates)
            ->get();
    }
    public static function getUsdEurRate(CarbonImmutable $current_date):BigDecimal
    {
        $dateKey = $current_date->toDateString();

        // 1. Verifica se il tasso è già in cache per questa data
        if (isset(self::$rateCache[$dateKey])) {
            return self::$rateCache[$dateKey];
        }

        // Se non è in cache, procedi con la query al database
        $oneMonthAgo = $current_date->subMonth();
        $sql = "SELECT date, value FROM eur_usd WHERE date <= :currentDate AND date >= :oneMonthAgo ORDER BY date DESC";

        $params = [
            'currentDate' => $dateKey,
            'oneMonthAgo' => $oneMonthAgo->toDateString(),
        ];

        $eurusdCoefficients = DB::connection('alternate')->select($sql, $params);
        $rate = BigDecimal::zero();

        foreach ($eurusdCoefficients as $eu) {
            $euDate = CarbonImmutable::parse($eu->date);
            if ($euDate <= $current_date) {
                $rate = BigDecimal::of($eu->value);
                break;
            }
        }

        if ($rate->isZero()) {
            throw new Exception("EUR/USD rate 0: No valid exchange rate found for the period ending on {$dateKey}.");
        }

        // 2. Memorizza il tasso in cache prima di ritornarlo
        self::$rateCache[$dateKey] = $rate;

        return $rate;
    }

    /**
     * @throws Exception
     */
    public static function convertUsdToEur(BigDecimal $usdAmount, CarbonImmutable $current_date): BigDecimal
    {
        $eurRate = self::getUsdEurRate($current_date);

        return BigDecimal::of($usdAmount)
            ->dividedBy($eurRate->multipliedBy(BigDecimal::of(1.02)), 2,RoundingMode::HALF_EVEN);

    }

    public static function getEurEcpmCostList(CarbonImmutable $currentDate, string $tableName, string $inventory, ?BigDecimal $ecpm = null): array
    {
        $ecpmString = "ecpm";
        if ($ecpm !== null) {
            // In PHP, i float usano il punto come separatore decimale, non è necessario str_replace
            // Formattiamo il valore come stringa per l'interpolazione SQL (rischio SQL Injection!)
            $ecpmString = "'" . (string)$ecpm . "'";
        }
        // Formato data per la query SQL
        $dateString = $currentDate->format('Y-m-d');

        // Uso di Heredoc/Nowdoc per la query con interpolazione
        $query = "
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
                '" . str_replace('SSP_', '', strtoupper($tableName)) . "' AS ssp,
                domain,
                date,
                '{$inventory}' as inventory_calc,
                'FEE' as iddeal_calc,
                0 as requests,
                0 as impressions,
                0 as clicks,
                sum(-1 * FLOOR(impressions * ".$ecpmString." / 10) / 100) as revenue
                FROM
                {$tableName}
                WHERE date = '{$dateString}'
                group by domain, date, inventory_calc, iddeal_calc
            ) calc
        ";

        // Assumendo Laravel/DB::select
        $costList = DB::connection('alternate')->select($query);

        // Traduzione di Console.WriteLine
        // error_log(strtoupper($tableName) . ": " . count($costList) . " record");

        return $costList;
    }

    public static function getUsdEcpmCostList(CarbonImmutable $currentDate, string $tableName, string $inventory, ?BigDecimal $ecpm = null): array
    {
        // SQL query logic is identical to EUR function, so we reuse the core logic
        $ecpmString = "ecpm";
        if ($ecpm !== null) {
            // In PHP, i float usano il punto come separatore decimale, non è necessario str_replace
            // Formattiamo il valore come stringa per l'interpolazione SQL (rischio SQL Injection!)
            $ecpmString = "'" . (string)$ecpm . "'";
        }
        // Formato data per la query SQL
        $dateString = $currentDate->format('Y-m-d');

        // Uso di Heredoc/Nowdoc per la query con interpolazione
        $query = "
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
                '" . str_replace('SSP_', '', strtoupper($tableName)) . "' AS ssp,
                domain,
                date,
                '{$inventory}' as inventory_calc,
                'FEE' as iddeal_calc,
                0 as requests,
                0 as impressions,
                0 as clicks,
                sum(-1 * FLOOR(impressions * ".$ecpmString." / 10) / 100) as revenue
                FROM
                {$tableName}
                WHERE date = '{$dateString}'
                group by domain, date, inventory_calc, iddeal_calc
            ) calc
        ";

        // Assumendo Laravel/DB::select
        $costList = DB::connection('alternate')->select($query);


        $dateString = $currentDate->format('Y-m-d');

        // Fetch EUR/USD coefficients
        // Traduzione della query C# con binding dei parametri in Laravel/DB:
        $eurusdCoefficients = DB::connection('alternate')->select("
            SELECT * from eur_usd
            WHERE Date <= '" . $dateString . "' AND Date >= DATE_SUB('" . $dateString . "', INTERVAL 1 MONTH)
            order by date desc
        ");

        foreach ($costList as $item) {
            $converted = 0.0;
            // Usiamo (float) per garantire che i calcoli siano eseguiti con numeri a virgola mobile
            $originalEntrate = BigDecimal::of($item->revenue);

            foreach ($eurusdCoefficients as $eu) {
                // Conversione da stringa/timestamp a oggetto DateTime per il confronto
                $euDate = CarbonImmutable::parse($eu->date);
                $itemData = CarbonImmutable::parse($item->date);

                // C# eu.Date <= item.Data comparison
                if ($euDate <= $itemData) {
                    // C# Math.Round(item.Entrate * 1 / (eu.Value * 1.02m), 2) translated
                    $conversion = BigDecimal::of($eu->value);
                    if ($conversion->isZero()) {
                        $converted = 0.0;
                    } else
                        $converted = BigDecimal::of($originalEntrate)
                            ->dividedBy($conversion->multipliedBy(BigDecimal::of(1.02)), 2, RoundingMode::HALF_EVEN)
                            ->toFloat();
                }
                break;
            }


        //    if ($converted == 0.0 && $originalEntrate != 0.0) {
        //        throw new Exception("eurusd coefficient for date " . $item->data . " not found.");
        //    }

            $item->revenue = $converted;
        }


        return $costList;
    }


}
