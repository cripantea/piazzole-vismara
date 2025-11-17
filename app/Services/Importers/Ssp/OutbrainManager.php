<?php
namespace App\Services\Importers\Ssp;

use App\Services\Importers\Interfaces\CacheBuilderInterface;
use App\Services\SspReader;
use Brick\Math\BigDecimal;
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

class OutBrainValueDTO
{
    public function __construct(public mixed $Value) {}
}

class OutBrainItemDTO
{
    public function __construct(
        public OutBrainValueDTO $Day,
        public OutBrainValueDTO $Widget,
        public OutBrainValueDTO $Section,
        public ?OutBrainValueDTO $TotalPvs,
        public ?OutBrainValueDTO $PaidReqs,
        public ?OutBrainValueDTO $TotalClicks,
        public ?OutBrainValueDTO $Revenue
    ) {}
}

class OutbrainManager extends JsonParser implements SspDownloadInterface, CacheBuilderInterface
{
    public function __construct(
        public ?CarbonImmutable $date=null,
        public ?string $domain=null,
        public ?string $ad_unit=null,
        public ?int $impressions=null,
        public ?int $clicks=null,
        public ?BigDecimal $revenue=null,
    ){}

    public static function downloadFromAPI(?string $fromDate, ?string $toDate, ?string $fileName)
    {
        $client = new Client([
            'base_uri' => 'https://api.outbrain.com/engage/v2/',
            'timeout'  => 10.0,
        ]);

        $baseUrl = 'https://api.outbrain.com/engage/v1';

        $login = function($url){
            $apiKey = config('ssp.outbrain');
            $response = Http::withBasicAuth(
                    explode(':', $apiKey)[0],
                    explode(':', $apiKey)[1]
                )->get("{$url}/login");

            $response->throw();
            $token = json_decode($response->body())->{'OB-TOKEN-V1'};
            return $token;
        };


        $getPublishers = function(string $token, Client $client){
            $response = $client->get('lookups/publishers', [
                'headers' => [
                    'OB-TOKEN-V1' => $token,
                    'Accept' => 'application/json',
                ]
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            return $body['publishers'] ?? [];

        };


        $getData = function(string $token, Client $client, array $publisher, CarbonImmutable $fromDate, CarbonImmutable $toDate){

            $queryString=http_build_query([
                    'entityId' => $publisher['id'],
                    'breakdowns' => 'day,widget,section', // I breakdowns sono una stringa separata da virgole
                    'currencyCode' => 'EUR',
                    'fromDate' => $fromDate->format('Ymd'),
                    'toDate' => $toDate->format('Ymd'),
            ]);

            $response = $client->get('reports/outbrain?'.$queryString, [
                'headers' => [
                    'OB-TOKEN-V1' => $token,
                    'Accept' => 'application/json',
                ]
            ]);

            $body = json_decode($response->getBody()->getContents(), true);
            $itemsJson = $body['items'];

            $mappedItems = array_map(function ($item) {
                return new OutBrainItemDTO(
                    new OutBrainValueDTO($item['day']['value'] ?? null),
                    new OutBrainValueDTO($item['widget']['value'] ?? ''),
                    new OutBrainValueDTO($item['section']['value'] ?? ''),
                    isset($item['totalPvs']) ? new OutBrainValueDTO($item['totalPvs']['value']) : new OutBrainValueDTO(0),
                    isset($item['paidReqs']) ? new OutBrainValueDTO($item['paidReqs']['value']) : new OutBrainValueDTO(0),
                    isset($item['totalClicks']) ? new OutBrainValueDTO($item['totalClicks']['value']) : new OutBrainValueDTO(0),
                    isset($item['revenue']) ? new OutBrainValueDTO($item['revenue']['value']) : new OutBrainValueDTO(0)
                );
            }, $itemsJson);




            return [
                'Domain' => $publisher['name'],
                'Items' => $mappedItems
            ];

        };


        $tokenAccess=$login($baseUrl);

        $publishers = $getPublishers($tokenAccess, $client);

        $startDate = $fromDate ?? CarbonImmutable::now()->subDays(15)->format('Y-m-d');
        $endDate = $toDate ?? CarbonImmutable::now()->format('Y-m-d');

        $resultList=[];
        $currentDate = CarbonImmutable::parse($startDate);

        while($currentDate<$endDate){
            foreach($publishers as $p){

                $data=$getData($tokenAccess, $client, $p, $currentDate, $currentDate);

                $resultList[]=$data;
            }

            $currentDate=$currentDate->addDay();
        }

        $responseString = json_encode($resultList);

        $filePath = $fileName ?? SspReader::generateOutFileName("outbrain", "json", $startDate, $endDate);
        file_put_contents($filePath, $responseString);
    }

    public static $mapping = [
        'date' => 'Data',
        'domain' => 'Dominio',
        'ad_unit' => 'AdUnit',
        'clicks' => 'Clicks',
        'impressions' => 'Impressions',
        'revenue' => 'Revenue',
    ];

    public function convert(array $input): Collection
    {

        $result = collect();
        foreach ($input as $row)
        {

            $dominio = strtolower($row['Domain'] ?? '');

            if (str_contains($dominio, 'iltempo')) {
                $dominio = 'iltempo.it';
            } elseif (str_contains($dominio, 'liberoquotidiano')) {
                $dominio = 'liberoquotidiano.it';
            } else {
                $dominio = str_replace(
                    ['IT_', '(b4u-advertising)', '/'],
                    '',
                    $row['Domain'] ?? ''
                );
                $dominio = trim($dominio);
            };
            if(!isset($row['Items'])) continue;
            foreach ($row['Items'] as $it)
            {
                $d=$it['Day']['Value'];
                $adUnit=$dominio."_".$it['Widget']['Value'];
                $imp= isset($it['TotalPvs']['Value']) && $it['TotalPvs']['Value']>0? $it['TotalPvs']['Value']: $it['PaidReqs']['Value'];
                $clicks= $it['TotalClicks']['Value'];
                $rev=$it['Revenue']['Value'];

                $result->push(new self(
                    date: CarbonImmutable::createFromFormat('Ymd', $d),
                    domain: ($dominio !== "iltempo.it" && $dominio !== "liberoquotidiano.it") ? $it["Section"]['Value'] : $dominio,
                    ad_unit: $adUnit,
                    impressions: (int)$imp,
                    clicks: $clicks,
                    revenue: (BigDecimal::of(str_replace(",", ".", $rev)))
                ));
            }

        }
        $grouped = $result->groupBy(fn($item) => $item->date->format('Y-m-d') . '|' . $item->domain . '|' . $item->ad_unit)
                ->map(function ($items) {
                    $first = $items->first();

                    return new self(
                        date: $first->date,
                        domain: $first->domain,
                        ad_unit: $first->ad_unit,
                        impressions: $items->sum(fn($i) => $i->impressions),
                        clicks: $items->sum(fn($i) => $i->clicks),
                        revenue: $items->reduce(
                            fn($carry, $item) => $carry->plus($item->revenue),
                            BigDecimal::of('0')
                        )
                    );
                })
                ->values();

        return $grouped;
    }

    public function getDelimiter(): string{
        return "";
    }

    public function getTableName(): string{
        return "ssp_outbrain";
    }
    public function getCsvDelimiter(): string{
        return ";";
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
                     'OUTBRAIN' AS ssp,
                     domain,
                     date,
                     'DISPLAY' as inventory_calc,
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
