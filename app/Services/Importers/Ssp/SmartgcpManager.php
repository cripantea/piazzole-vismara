<?php
namespace App\Services\Importers\Ssp;

use App\Models\SspAniview;
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
use Modules\Importer\DTOs\AniviewDTO;
use App\Services\Importers\Parsers\CsvParser;
use App\Services\Importers\Parsers\JsonParser;

class SmartgcpManager extends CsvParser implements SspDownloadInterface, CacheBuilderInterface
{
    public function __construct(
        public ?CarbonImmutable $date=null,
        public ?string $domain=null,
        public ?string $ad_unit=null,
        public ?string $inventory=null,
        public ?string $id_deal=null,
        public ?string $deal_name=null,
        public ?int $impressions=null,
        public ?int $clicks=null,
        public ?BigDecimal $revenue=null,
    ){}

    public static function downloadFromAPI(?string $fromDate, ?string $toDate, ?string $fileName)
    {

        $startDate = $fromDate ?? Carbon::now()->subDays(7)->format('Y-m-d');
        $endDate = $toDate ?? Carbon::now()->format('Y-m-d');
        $credentials = config('ssp.smartgcp');

        $payload = [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'metrics' => [
                    ['field' => 'Impressions', 'outputName' => 'ImpressionsTrueCount', 'emptyValue' => '0'],
                    ['field' => 'Clicks', 'outputName' => 'Clicks', 'emptyValue' => '0'],
                    ['field' => 'PublisherGrossRevenuePublisherCurrency', 'outputName' => 'TotalPaidPriceNetworkCurrencyTrueCount', 'emptyValue' => '0'],
                ],
                'dimensions' => [
                    ['field' => 'Day', 'outputName' => 'Day', 'emptyValue' => '0'],
                    ['field' => 'AppSiteChannelUrl', 'outputName' => 'SiteUrl', 'emptyValue' => 'N/A'],
                    ['field' => 'AppOrSiteDomain', 'outputName' => 'Domain', 'emptyValue' => 'N/A'],
                    ['field' => 'HeaderBiddingTypeName', 'outputName' => 'HeaderBiddingTypeName', 'emptyValue' => 'N/A'],
                    ['field' => 'ServerSideBiddingCallerName', 'outputName' => 'ServerSideBiddingCallerName', 'emptyValue' => 'N/A'],
                    ['field' => 'PublisherExternalDealId', 'outputName' => 'DealExternalId', 'emptyValue' => 'N/A'],
                    ['field' => 'PublisherDealName', 'outputName' => 'DealName', 'emptyValue' => 'N/A'],
                    ['field' => 'FormatId', 'outputName' => 'FormatId', 'emptyValue' => '0'],
                    ['field' => 'FormatName', 'outputName' => 'FormatName', 'emptyValue' => 'N/A'],
                    ['field' => 'FormatType', 'outputName' => 'FormatType', 'emptyValue' => 'N/A'],
                    ['field' => 'VideoContentId', 'outputName' => 'VideoContentId', 'emptyValue' => 'N/A'],
                    ['field' => 'FormatTypeId', 'outputName' => 'ImpressionType', 'emptyValue' => '0'],
                ],
                'filters' => [],
                'useCaseId' => 'Holistic',
                'dateFormat' => "yyyy-MM-dd",
                'timezone' => 'Network',
                'onFinishEmails' => [],
                'onErrorEmails' => [],
                'ReportName' => 'Report ' . Carbon::now()->format('Y-m-d H:i:s'),
            ];

            $url = "https://supply-api.eqtv.io/insights/report-async";

            $auth = base64_encode($credentials);

            $response = Http::withHeaders([
                'Authorization' => "Basic {$auth}",
                'Content-Type' => 'application/json',
            ])->post($url, $payload);

            if (!$response->successful()) {
                return null;
            }

            $taskId = $response->json();
            if (!is_string($taskId)) {
                return null;
            }

            $csvUrl = "https://supply-api.eqtv.io/insights/report-async/{$taskId}";

            for ($count = 0; $count < 30; $count++) {

                sleep(10); // wait 10 seconds

                $csvResult = Http::withHeaders([
                    'Authorization' => "Basic {$auth}",
                ])->get($csvUrl);

                if (!$csvResult->successful()) {
                    continue;
                }

                $job = $csvResult->json();
                $instanceId = $job['instanceId'] ?? null;

                if (empty($instanceId)) {
                    continue;
                }

                // Fetch actual CSV data
                $csvDataResponse = Http::withHeaders([
                    'Authorization' => "Basic {$auth}",
                ])->get($instanceId);

                if (!$csvDataResponse->successful()) {
                    continue;
                }

                $csvData = $csvDataResponse->body();

                $filePath = $fileName ?? SspReader::generateOutFileName("smartgcp", "csv", $startDate, $endDate);
                file_put_contents($filePath, $csvData);
                break;


            }



    }

    public static $mapping = [
        'date' => 'Data',
        'domain' => 'Dominio',
        'ad_unit' => 'AdUnit',
        'id_deal' => 'IdDeal',
        'deal_name' => 'DealName',
        'inventory' => 'Inventory',
        'clicks' => 'Clicks',
        'impressions' => 'Impressioni',
        'revenue' => 'Entrate',
    ];
            // if (record.ServerSideBiddingCallerName.ToLower() == "aniview")
            // {
            //     return record.Domain.Trim().ToLower();
            // }

            // return record.SiteUrl.Trim().ToLower() != "n/a" ? record.SiteUrl.Trim().ToLower() : record.Domain.Trim().ToLower();

    public function trimUrlProtocol(string $s):string
    {
        if (str_contains($s, '://')) {
            // Remove the scheme and trim trailing slashes/spaces
            return trim(rtrim(substr($s, strpos($s, ':') + 3), "/ "), " ");
        } else {
            return $s;
        }
    }
    public function valutaCampiCalcolati(object $d): void
    {
        // Remove protocol from URL
        $d->domain = $this->trimUrlProtocol($d->domain); // uses the function from earlier

        // Fix specific charset-related string
        if ($d->deal_name === "deal_piątnica_jedzenie") {
            $d->deal_name = "deal_piatnica_jedzenie";
        }

        // Remove invisible characters and problematic encoding
        $d->deal_name = str_replace("​", "", $d->deal_name); // U+200B (zero-width space)
        $d->deal_name = str_replace("â", "a", $d->deal_name);

        // Remove heart emoji if present
        if (str_contains($d->deal_name, "❤️")) {
            $d->deal_name = str_replace("❤️", "", $d->DealName);
        }
    }
    public function convert(array $input): Collection
    {

        $header = $input['header'];
        $rows = $input['rows'];

        //$header = explode(';', $input['header'][0]);
        $result = collect();

        foreach ($rows as $riga)
        {

            $assoc = array_combine($header, $riga);
            if (! $assoc) {
                continue;
            }
            $revenue=str_contains($assoc['TotalPaidPriceNetworkCurrencyTrueCount'], 'e')
                    ? BigDecimal::zero()
                    : BigDecimal::of(str_replace(',', '.', $assoc['TotalPaidPriceNetworkCurrencyTrueCount']));

            if ($assoc['DealExternalId'] === "deal_piątnica_jedzenie") {
                $assoc['DealExternalId'] = "deal_piatnica_jedzenie";
            }

            // Remove invisible characters and problematic encoding
            $assoc['DealExternalId'] = str_replace("​", "", $assoc['DealExternalId']); // U+200B (zero-width space)
            $assoc['DealExternalId'] = str_replace("â", "a", $assoc['DealExternalId']);

            // Remove heart emoji if present
            if (str_contains($assoc['DealExternalId'], "❤️")) {
                $assoc['DealExternalId'] = str_replace("❤️", "", $assoc['DealExternalId']);
            }
            if (strtolower($assoc['HeaderBiddingTypeName']) == "server side header bidding"
                && strtolower($assoc['ServerSideBiddingCallerName']) != "prebidserver"
                && strtolower($assoc['ServerSideBiddingCallerName']) != "aniview")
            {
                continue;
            }

            $domain=$this->trimUrlProtocol(strtolower($assoc['ServerSideBiddingCallerName'])=='aniview' ?
                        trim(strtolower($assoc['Domain'])) :
                        (trim(strtolower($assoc['SiteUrl'])) != "n/a" ? trim(strtolower($assoc['SiteUrl'])) : trim(strtolower($assoc['Domain']))));

            if(true){

                $result->push(new self(
                    date: CarbonImmutable::parse($assoc['Day'] ?? $assoc['Day'] ?? now()),
                    domain: $domain==''?'empty_url':$domain,
                    ad_unit: $assoc['FormatName'],
                    inventory: $assoc['FormatType'],
                    id_deal: $assoc['DealExternalId'] == "0" ?  "N/A" : $assoc['DealExternalId'],///FormatName'],
                    deal_name: $assoc['DealName'],
                    impressions: (int)($assoc['ImpressionsTrueCount'] ?? 0),
                    clicks: (int)($assoc['Clicks'] ?? 0),
                    revenue: $revenue,
                ));
            }
        }

        $grouped = $result->groupBy(fn($item) => $item->date->format('Y-m-d') . '|' . $item->domain . '|' . $item->ad_unit . '|' . $item->inventory. '|' . $item->id_deal. '|' . $item->deal_name)
                ->map(function ($items) {
                    $first = $items->first();

                    return new self(
                        date: $first->date,
                        domain: $first->domain,
                        ad_unit: $first->ad_unit,
                        inventory: $first->inventory,
                        id_deal: $first->id_deal,
                        deal_name: $first->deal_name,
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
        return "HeaderBiddingTypeName";
    }

    public function getTableName(): string{
        return "ssp_smart";
    }
    public function getCsvDelimiter(): string{
        return ";";
    }

    public function getDomainCache(?CarbonImmutable $current_date): array
    {
        // TODO: Implement getDomainCache() method.
    }
}
