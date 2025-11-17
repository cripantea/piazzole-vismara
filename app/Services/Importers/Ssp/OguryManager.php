<?php
namespace App\Services\Importers\Ssp;

use App\Services\Importers\Helpers\ImportersHelper;
use App\Services\Importers\Interfaces\CacheBuilderInterface;
use App\Services\SspReader;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Google\Service\Dfareporting\Ad;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\Importers\Interfaces\SspDownloadInterface;
use App\Services\Importers\Parsers\CsvParser;
use App\Services\Importers\Parsers\JsonParser;

class OguryManager extends JsonParser implements SspDownloadInterface, CacheBuilderInterface
{
    public function __construct(
        public ?CarbonImmutable $date=null,
        public ?string $domain=null,
        public ?string $ad_unit=null,
        public ?string $inventory=null,
        public ?int $impressions=null,
        public ?int $requests=null,
        public ?BigDecimal $revenue=null,
    ){}

    public static function downloadFromAPI(?string $fromDate, ?string $toDate, ?string $fileName)
    {
        try{
            $token = config('ssp.ogury'); // Your bearer token here

            $baseUrl = 'https://exclusive-demand-report-api.ogury.co';

            // 1. First GET request to /assets
            $assetsResponse = Http::withToken($token)
                ->acceptJson()
                ->get("{$baseUrl}/assets");

            $assetsResponse->throw(); // Throws exception if not 200

            $responseData = "[" . $assetsResponse->body(); // open array literal like in C# with `[` + content
            $startDate = $fromDate ?? Carbon::now()->subDays(7)->format('Y-m-d');
            $endDate = $toDate ?? Carbon::now()->format('Y-m-d');
            // 2. Build the JSON payload for the POST
            $payload = [
                'filters' => [
                    'from' => $startDate,
                    'to' => $endDate,
                ],
                'groups' => ['ad_unit', 'date'],
            ];

            // 3. Second POST request to /stats
            $statsResponse = Http::withToken($token)
                ->acceptJson()
                ->post("{$baseUrl}/stats", $payload);

            $statsResponse->throw();

            $responseData .= "," . $statsResponse->body() . "]";

            // 4. Write to file
            $filePath = $fileName ?? SspReader::generateOutFileName("ogury", "json", $startDate, $endDate);
            file_put_contents($filePath, $responseData);

        } catch (\Throwable $e) {
            Log::error('Ogury API error: ' . $e->getMessage());
        }
    }

    public static $mapping = [
        'date' => 'Data',
        'domain' => 'Dominio',
        'ad_unit' => 'AdUnit',
        'inventory' => 'Inventory',
        'impressions' => 'Impressioni',
        'revenue' => 'Entrate',
        'requests' => 'Richieste'
    ];

    public function convert(array $json): Collection
    {

        //$json = json_decode($input, true);

        $result = collect();
        $adunits = [];

        // Step 1: estrai asset/ad_units
        $assets = $json[0]['assets'] ?? [];

        foreach ($assets as $asset) {
            foreach ($asset['ad_units'] as $detail) {
                $adunits[] = [
                    'domain' => $asset['bundle'],
                    'id' => $detail['id'],
                    'name' => $detail['name'],
                    'uniqueName' => $asset['bundle'] . '_' . (str_contains(strtolower($detail['name']), 'display') ? 'DISPLAY' : 'VIDEO') . '_' . $detail['id'],
                    'inventory' => str_contains(strtolower($detail['name']), 'display') ? 'DISPLAY' : 'VIDEO',
                ];
            }
        }

        // Step 2: leggi le revenue per adunit
        for ($i = 1, $iMax = count($json); $i < $iMax; $i++) {
            $stats = $json[$i]['stats'] ?? [];

            foreach ($stats as $child) {
                $adUnitId = $child['group']['ad_unit'] ?? null;
                $adunit = collect($adunits)->firstWhere('id', $adUnitId);

                if (!$adunit) {
                    continue; // salta se non c'è matching
                }

                // Parsing valori
                $entrateRaw = $child['metrics']['revenues'] ?? '0';
                $entrate = stripos($entrateRaw, 'e') !== false
                    ? 0.0
                    : (float) str_replace(',', '', $entrateRaw);
                $result->push(new self(
                    date: CarbonImmutable::parse($child['group']['date'] ?? now()),
                    domain: $adunit['domain'] ?? '',
                    ad_unit: $adunit['uniqueName'],
                    inventory: str_contains(strtolower($adunit['name']), 'display') ? 'DISPLAY' : 'VIDEO',
                    impressions: (int) ($child['metrics']['impressions'] ?? 0),
                    requests: (int) ($child['metrics']['requests'] ?? 0),
                    revenue: BigDecimal::of
                    (BigDecimal::of(str_replace(",", ".", $child['metrics']['revenues'] ?? '0')))
                ));
            }
        }

        return $result;
    }

    public function getDelimiter(): string{
        return "";
    }

    public function getTableName(): string{
        return "ssp_ogury";
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
                     'OGURY' AS ssp,
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
