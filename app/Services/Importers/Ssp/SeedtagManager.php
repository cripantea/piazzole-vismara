<?php
namespace App\Services\Importers\Ssp;

use App\Models\SspAniview;
use App\Services\Importers\Helpers\ImportersHelper;
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
use App\Services\Importers\Interfaces\CacheBuilderInterface;
use Modules\Importer\DTOs\AniviewDTO;
use App\Services\Importers\Parsers\CsvParser;
use App\Services\Importers\Parsers\JsonParser;

class SeedtagManager extends CsvParser implements SspDownloadInterface, CacheBuilderInterface
{
    public function __construct(
        public ?CarbonImmutable $date=null,
        public ?string $domain=null,
        public ?string $ad_unit=null,
        public ?string $inventory=null,
        public ?int $impressions=null,
        public ?int $clicks=null,
        public ?BigDecimal $revenue=null,
    ){}

    public static function downloadFromAPI(?string $fromDate, ?string $toDate, ?string $fileName)
    {

        $list = [];
        $seedtagData = [];
        $apiKey = config('ssp.seedtag');
        $startDate = $fromDate ?? Carbon::now()->subDays(7)->format('Y-m-d');
        $endDate = $toDate ?? Carbon::now()->format('Y-m-d');
        $tokenResponse = Http::get("https://login.seedtag.com/api/token", [
            'api_key' => $apiKey,
        ]);
        $tokenResponse->throw();
        $accessToken = $tokenResponse->json('accessToken');


        $client = Http::withToken($accessToken);

    $publishers = [];
    $pubResponse = $client->get("https://publishers.api.seedtag.com/publishers", [
        'pageSize' => 1000,
    ]);
    $pubResponse->throw();
    foreach ($pubResponse->json('data') as $item) {
        $publishers[$item['id']] = $item['name'];
    }

    $pageNum = 0;
    do {
        if ($pageNum > 10) {
            throw new \Exception("Seedtag possibly in infinite loop");
        }

        $payload = [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'pageSize' => '1000',
            'pageNum' => (string)$pageNum,
            'splits' => ['day', 'publisherId', 'productFamily', 'adunitType'],
            'filters' => [],
        ];

        $reportResponse = $client->post("https://publishers.api.seedtag.com/report", $payload);
        $reportResponse->throw();
        $json = $reportResponse->json();

        if (($json['total'] ?? 0) > 0) {
            foreach ($json['data'] as $item) {
                $seedtagData[] = [
                    'date' => $item['_id']['day'],
                    'publisher' => $publishers[$item['_id']['publisherId']] ?? $item['_id']['publisherId'],
                    'revenue' => str_replace(',', '.', $item['revenue']),
                    'impressions' => $item['impressions'],
                    'clicks' => $item['clicks'],
                    'placement' => $item['_id']['adunitType'],
                    'format' => $item['_id']['productFamily'],
                ];
            }
            $pageNum++;
        } else {
            break;
        }

    } while (true);
    $filePath = $fileName ?? SspReader::generateOutFileName("seedtag", "csv", $startDate, $endDate);
    $fp = fopen($filePath, 'w');

    $delimiter = ';';

    fputcsv($fp, array_keys($seedtagData[0]), $delimiter);

    foreach ($seedtagData as $row) {
        fputcsv($fp, $row, $delimiter);
    }
//echo $filePath;
    fclose($fp);
    return $seedtagData;

    }

    public static $mapping = [
        'date' => 'Data',
        'domain' => 'Dominio',
        'ad_unit' => 'AdUnit',
        'inventory' => 'Inventory',
        'clicks' => 'Clicks',
        'impressions' => 'Impressioni',
        'revenue' => 'Entrate',
    ];

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
            if(true){
                $result->push(new self(
                    date: CarbonImmutable::parse($assoc['date'] ?? $assoc['date'] ?? now()),
                    domain: $assoc['publisher'] ?? '',
                    ad_unit: 'Seedtag_'.(($assoc['date']>='2025-07-01' && strtolower($assoc['placement'])=='instream')?'video_':'').($assoc['publisher'] ?? ''),
                    inventory: $assoc['date']<'2025-07-01' ?
                                    'DISPLAY':
                                    (strtolower($assoc['placement'])=='instream' ? 'VIDEO' : 'DISPLAY'),
                    impressions: (int)($assoc['impressions'] ?? 0),
                    clicks: (int)($assoc['clicks'] ?? 0),
                    revenue: (BigDecimal::of(str_replace(",", ".", $assoc['revenue']))->toScale(2, RoundingMode::DOWN) )
                ));
            }
        }

        $grouped = $result->groupBy(fn($item) => $item->date->format('Y-m-d') . '|' . $item->domain . '|' . $item->ad_unit . '|' . $item->inventory)
                ->map(function ($items) {
                    $first = $items->first();

                    return new self(
                        date: $first->date,
                        domain: $first->domain,
                        ad_unit: $first->ad_unit,
                        inventory: $first->inventory,
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
        return "clicks";
    }

    public function getTableName(): string{
        return "ssp_seedtag";
    }
    public function getCsvDelimiter(): string{
        return ";";
    }

    // Dummy implementation con errore intenzionale
    public function getDomainCache(?\Carbon\CarbonImmutable $current_date): array
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
                    'SEEDTAG' AS ssp,
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

//        foreach ($domainList as $item) {
//            $a=(ImportersHelper::convertUsdToEur(BigDecimal::of($item->revenue), $current_date));
//            $item->revenue = $a;
//        }
        return [
            'domains' => $domainList,
            'deals' => [],
        ];

    }
}
