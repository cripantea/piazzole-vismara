<?php
namespace App\Services\Importers\Ssp;

use App\Services\Importers\Helpers\ImportersHelper;
use App\Services\Importers\Interfaces\CacheBuilderInterface;
use App\Services\SspReader;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\Importers\Interfaces\SspDownloadInterface;
use App\Services\Importers\Parsers\CsvParser;

class ConnectadManager extends CsvParser implements SspDownloadInterface, CacheBuilderInterface
{
    public function __construct(
        public ?CarbonImmutable $date=null,
        public ?string $domain=null,
        public ?string $ad_unit=null,
        public ?string $inventory=null,
        public ?int $impressions=null,
        public ?BigDecimal $revenue=null,
    ){}

    public static function downloadFromAPI(?string $fromDate, ?string $toDate, ?string $fileName)
    {

        $apiKey = config('ssp.connectad');


        $startDate = empty($fromDate) ? Carbon::now()->subDays(7)->format('Y-m-d'):$fromDate;
        $endDate = Carbon::now()->format('Y-m-d');
        $url = "https://insight.connectad.io/APIreport?pid={$apiKey}&start={$startDate}&end={$endDate}";

        try {
            $response = Http::get($url);

            if ($response->successful()) {
                $responseString = $response->body();

                $filePath = $fileName ?? SspReader::generateOutFileName("connectad", "csv", $startDate, $endDate);
                file_put_contents($filePath, $responseString);
            }
        } catch (\Exception $e) {
            Log::error('Connectad fetch failed: ' . $e->getMessage());
        }
    }

    public static $mapping = [
        'date' => 'Data',
        'domain' => 'Dominio',
        'ad_unit' => 'AdUnit',
        'inventory' => 'Inventory',
        'impressions' => 'Impressioni',
        'revenue' => 'Entrate',
    ];

    public function convert(array $parsed): Collection
    {
        $header = $parsed['header'];
        $rows = $parsed['rows'];

        $result = collect();
        foreach ($rows as $riga)
        {
            $assoc = array_combine($header, $riga);
            if (! $assoc) {
                continue;
            }
            $revenue = BigDecimal::of(str_replace(",", ".", $assoc['Revenue']));
            if(!$revenue->isZero()){
                $result->push(new self(
                    CarbonImmutable::parse($assoc['Date'] ?? $assoc['date'] ?? now()),
                    $assoc['Website'] ?? '',
                    ($assoc['Website'] ?? '')."_".$assoc['Format'],
                    'DISPLAY',
                    (int)($assoc['Impressions sold'] ?? 0),
                    (BigDecimal::of(str_replace(",", ".", $assoc['Revenue'])))
                ));
            }
        }

        return $result;
    }

    public function getDelimiter(): string{
        return "Requests";
    }

    public function getTableName(): string{
        return "ssp_connectad";
    }
    public function getCsvDelimiter(): string{
        return ",";
    }

    /**
     * @throws \Exception
     */
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
                     'CONNECTAD' AS ssp,
                     domain,
                     date,
                     inventory as inventory_calc,
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
            $a=(ImportersHelper::convertUsdToEur(BigDecimal::of($item->revenue), $current_date));
            $item->revenue = $a;
        }


        return [
            'domains' => $domainList,
            'deals' => [],
        ];
    }
}
