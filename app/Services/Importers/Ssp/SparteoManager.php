<?php
namespace App\Services\Importers\Ssp;


use App\Services\Importers\Interfaces\CacheBuilderInterface;
use App\Services\SspReader;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\Importers\Interfaces\SspDownloadInterface;
use App\Services\Importers\Parsers\CsvParser;
use App\Services\Importers\Parsers\JsonParser;

class SparteoManager extends JsonParser implements SspDownloadInterface, CacheBuilderInterface
{
    public function __construct(
        public ?CarbonImmutable $date=null,
        public ?string $domain=null,
        public ?string $ad_unit=null,
        public ?string $inventory=null,
        public ?int $impressions=null,
        public ?BigDecimal $revenue=null,
    ){}

    public static function downloadFromAPI(?string $fromDate, ?string $toDate, ?string $fileName=null)
    {

        $username = config('ssp.sparteo.username');
        $password = config('ssp.sparteo.password');
        $startDate = $fromDate ?? Carbon::now()->subDays(7)->format('Y-m-d');
        $endDate = $toDate ?? Carbon::now()->format('Y-m-d');
        $tokenResponse = Http::post("https://api.meetscale.com/v1/login", [
            'username' => $username,
            'password' => $password,
        ]);



        $tokenResponse->throw();
        $accessToken = $tokenResponse->json('access_token');

        $client = Http::withToken($accessToken);


        $startDate = $fromDate ?? Carbon::now()->subDays(7)->format('Y-m-d');
        $endDate = $toDate ?? Carbon::now()->format('Y-m-d');

        // Definisci il body della richiesta come nell'esempio JSON
        $requestBody = [
            "columns" => ["page_domain", "media_type"],
            "metrics" => ["imps", "partner_revenue", "rpm_impression"],
            "filters" => [
                ["col" => "event_date", "op" => ">=", "val" => $startDate],
                ["col" => "event_date", "op" => "<", "val" => $endDate]
            ],
            "orderby" => [
                [
                    "col" => "event_date",
                    "dir" => "asc"
                ]
            ]
        ];

        $dataResponse = Http::withToken($accessToken)->post("https://api.meetscale.com/v1/data", $requestBody);

        $dataResponse->throw();

        $responseData = $dataResponse->json();


        $filePath = $fileName ?? SspReader::generateOutFileName("sparteo", "json", $fromDate, $toDate);
        //dd($filePath);
        file_put_contents($filePath, json_encode($responseData ?? []));


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
        $rows = $parsed['results'];
        $result = collect();
        foreach ($rows as $riga) {

            try {
                $result->push(new self(
                    date:CarbonImmutable::parse($riga['event_date']),
                    domain: $riga['page_domain'],
                    ad_unit: $riga['page_domain']."_SPARTEO_".strtoupper($riga['media_type']),
                    inventory: strtoupper($riga['media_type']),
                    impressions: $riga['imps'],
                    revenue: BigDecimal::of($riga['partner_revenue'])->toScale(2, RoundingMode::DOWN),
                ));

            }catch (\Exception $e) {
                echo $e->getTraceAsString();
            }
        }


        return $result;
    }

    public function getDelimiter(): string{
        return "clicks";
    }

    public function getTableName(): string{
        return "ssp_sparteo";
    }
    public function getCsvDelimiter(): string{
        return ";";
    }

    public function getDomainCache(?CarbonImmutable $current_date): array
    {
        $domainQueryString="
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
                  'SPARTEO' AS ssp,
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
            GROUP BY SSP , date , domain, inventory_calc, idDeal_calc) calc";



        $domainQueryResult = DB::connection('alternate')->select($domainQueryString);

        $domainQueryCollection = collect($domainQueryResult);

        $domainList = $domainQueryCollection->toArray();

        return [
            'domains' => $domainList,
            'deals' => [],
        ];

    }
}
