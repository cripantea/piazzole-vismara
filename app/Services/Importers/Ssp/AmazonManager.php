<?php

namespace App\Services\Importers\Ssp;

use App\Services\Importers\Helpers\ImportersHelper;
use App\Services\Importers\Interfaces\CacheBuilderInterface;
use App\Services\Importers\Interfaces\CacheBuildInterface;
use Carbon\CarbonImmutable;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use App\Services\Importers\Parsers\CsvParser;
use App\Services\Importers\Interfaces\SspDownloadInterface;
use App\Services\Importers\Parsers\JsonParser;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Services\SspReader;
use Illuminate\Support\Facades\Storage;


class AmazonManager extends JsonParser  implements SspDownloadInterface, CacheBuilderInterface
{
    public function __construct(
        public ?CarbonImmutable $date = null,
        public ?string $domain = null,
        public ?string $ad_unit = null,
        public ?string $inventory = null,
        public ?string $id_deal = null,
        public ?string $deal_name = null,
        public ?int $impressions = null,
        public ?BigDecimal $revenue = null,
    ) {}

    public static $mapping = [
        'date' => 'Data',
        'domain' => 'Dominio',
        'ad_unit' => 'AdUnit',
        'inventory' => 'Inventory',
        'id_deal' => 'IdDeal',
        'deal_name' => 'DealName',
        'impressions' => 'Impressioni',
        'revenue' => 'Entrate',
    ];


    // public static function ndjsonToJson(string $ndjson): string {
    //     $items = [];
    //     foreach (preg_split("/\r\n|\n|\r/", $ndjson) as $line) {
    //         $line = trim($line);
    //         if ($line === '') continue;
    //         $items[] = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    //     }
    //     return json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    // }

    public static function downloadFromAPI(string|null $fromDate, string|null $toDate, ?string $fileName)
    {
        $temp = null;

        try {

            $prefix=config('s3.amazon.aws_bucket_prefix');
            $pathTemplate= config('s3.amazon.aws_path');

            $today= CarbonImmutable::now()->format('Ymd');


            $path=str_replace('**date**', $today, $pathTemplate);

            $fullPath= "{$prefix}/{$path}";

            $files=Storage::disk('s3')->files($fullPath);

            $filePath=$files[0];


            $temp = Storage::disk('s3')->get($filePath);

        } catch (\Throwable $e) {
            // swallow like the C# example; or log if you prefer
            $temp = null;
        }


        if($temp)
        {
            $normalJson = $temp;
            $filePath = $fileName ?? SspReader::generateOutFileName("amazon", "json", $today, $today);
            file_put_contents($filePath, $normalJson);
            //return $temp;
        }
        // $responseString = json_encode($resultList);


    }

    public function convert(array $parsed): Collection
    {
        $rows = $parsed;

        $result = collect();
        foreach ($rows as $riga) {

            $date=CarbonImmutable::createFromFormat('Ymd', $riga["Date"]);
            $domain=$riga["Site Name"];
            $ad_unit=$riga["Slot Name"];
            $deal_name=$riga["Deal Name"] ?? '-';
            if($deal_name=='-'){
                $deal_name = 'OPEN MARKET';
            }
            $inventory='DISPLAY';
            if(str_starts_with(strtolower($ad_unit), 'videoslot')) $inventory='VIDEO';
            if(str_starts_with(strtolower($ad_unit), 'outstream') && $date >= CarbonImmutable::createFromDate(2023, 2, 1, 'Europe/Rome')) $inventory='VIDEO';
            $deal_id=($deal_name === 'OPEN MARKET')
                        ? '0'
                        : 'AMAZON_' . strtoupper(md5($deal_name));

            $impressions=(int) $riga["Impressions"];
            if($impressions>0){
                $result->push(new self(
                        date: $date,
                        domain: $domain,
                        ad_unit: $ad_unit,
                        inventory: $inventory,
                        id_deal: $deal_id,
                        deal_name: $deal_name,
                        impressions: $impressions,
                        revenue: BigDecimal::of($riga["Earnings"]),
                ));
            }

        }
        $grouped = $result->groupBy(fn($item) => $item->date->format('Y-m-d') . '|' . $item->domain . '|' . $item->ad_unit. '|' . $item->deal_name)
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
                        revenue: $items->reduce(
                            fn($carry, $item) => $carry->plus($item->revenue),
                            BigDecimal::of('0')
                        )->toScale(2, RoundingMode::DOWN)
                    );
                })
                ->values();

        return $grouped;
    }

    public function getDelimiter(): string
    {
        // --- MARKER: CUSTOM DELIMITER LOGIC REQUIRED HERE ---
        // This method should return the delimiter specific to your CSV file.
        // Example: return "AdUnit";
        // Example: return "another_csv_specific_delimiter";
        // --- END MARKER ---
        return ""; // Placeholder
    }

    public function getTableName(): string
    {
        return "ssp_amazon";
    }

    public function getCsvDelimiter(): string
    {
        // --- MARKER: CUSTOM CSV DELIMITER LOGIC REQUIRED HERE ---
        // This method should return the CSV field delimiter (e.g., ",", "\t", ";").
        // Example: return "\t"; // For tab-separated values
        // Example: return ","; // For comma-separated values
        // --- END MARKER ---
        return ""; // Placeholder
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
                    'AMAZON' AS ssp,
                    domain,
                    date,
                    IF(inventory = 'display', 'DISPLAY', 'VIDEO') AS inventory_calc,
                    IF(id_deal IN ('0','-NA-'), 'OPEN MARKET', id_deal) AS iddeal_calc,
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

            $idDealList = $domainQueryCollection
                ->where('id_deal', '!=', 'OPEN MARKET')
                ->pluck('id_deal')
                ->unique()
                ->values()
                ->all();

            $domainList = $domainQueryCollection->toArray();
            $dnList = [];

            if (count($idDealList) > 0) {
                // Load deals from DB
                $dealsOnDb = DB::connection('alternate')->table('deal')
                    ->whereIn('id', $idDealList)
                    ->where('ssp', 'AMAZON')
                    ->get();

                foreach ($dealsOnDb as $deal) {
                    if (!is_null($deal->dn)) {
                        foreach ($domainList as &$item) {
                            if ($item->id_deal == $deal->id) {
                                $valoreDn = BigDecimal::of($item->revenue)->multipliedBy(BigDecimal::of($deal->dn))->toScale(2, RoundingMode::HALF_EVEN);
                                $dnList[] = [
                                    'date' => $item->date,
                                    'domain' => $item->domain,
                                    'id_deal' => $item->id_deal,
                                    'inventory' => $item->inventory,
                                    'ssp' => $item->ssp,
                                    'value' => $valoreDn
                                ];
                                $item->revenue = BigDecimal::of($item->revenue)->minus($valoreDn);
                            } else {
                                $item->revenue = BigDecimal::of($item->revenue);
                            }
                        }
                        unset($item); // break reference
                    } else {
                        $domainList = array_filter($domainList, fn($x) => $x->id_deal != $deal->id);
                    }
                }
            }

        $conversionDate = CarbonImmutable::now();

// Conversione di $amazonList
        foreach ($domainList as $item) {
            $a=(ImportersHelper::convertUsdToEur($item->revenue, $current_date));
            $item->revenue = $a;
        }

// Conversione di $dnList

        foreach ($dnList as &$item) {
            $item['value']= ImportersHelper::convertUsdToEur($item['value'], $current_date);
        }

        return [
            'domains' => $domainList,
            'deals' => array_filter($dnList, fn($x) => $x['value']->compareTo(BigDecimal::zero()) > 0),
        ];
    }

}
