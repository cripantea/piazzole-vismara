<?php

namespace App\Services\Importers\Ssp;

use App\Services\Importers\Helpers\ImportersHelper;
use App\Services\Importers\Interfaces\CacheBuilderInterface;
use App\Services\SspReader;
use Carbon\CarbonImmutable;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use App\Services\Importers\Interfaces\SspDownloadInterface;
use App\Services\Importers\Parsers\CsvParser;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

use Illuminate\Support\Facades\Storage;


class TtdManager extends CsvParser  implements SspDownloadInterface, CacheBuilderInterface
{
    public function __construct(
        public ?CarbonImmutable $date = null,
        public ?string $domain = null,
        public ?string $ad_unit = null,
        public ?string $inventory = null,
        public ?int $impressions = null,
        public ?BigDecimal $revenue = null,
    ) {}

    public static $mapping = [
        'date' => 'Data',
        'domain' => 'Dominio',
        'ad_unit' => 'AdUnit',
        'inventory' => 'Inventory',
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

            $prefix=config('s3.ttd.aws_bucket_prefix');
            $path= config('s3.ttd.aws_path');
            $fileNamePrefix = config('s3.ttd.aws_filename');

            $today= CarbonImmutable::now()->format('Y-m-d');


            $fileNameAmazon=$path.$fileNamePrefix.$today.".csv";


            $temp = Storage::disk('s3ttd')->get($fileNameAmazon);

        } catch (\Throwable $e) {
            // swallow like the C# example; or log if you prefer
            $temp = null;
        }


        if($temp)
        {
            $normalJson = $temp;
            $filePath = $fileName ?? SspReader::generateOutFileName("ttd", "csv", $today, $today);
            file_put_contents($filePath, $normalJson);
            //return $temp;
        }
        // $responseString = json_encode($resultList);


    }

    public function convert(array $parsed): Collection
    {
        $header = $parsed['header'];
        $rows = $parsed['rows'];


        $result = collect();
        foreach ($rows as $riga) {
            $assoc = array_combine($header, $riga);
            if (! $assoc) {
                continue;
            }


            $date=CarbonImmutable::parse($assoc["Date"]);
            $domain=$assoc["Property"];
            $adunit = explode('#', trim($assoc['PlacementId']))[0];
            $inventory=strtoupper($assoc['MediaType']);
            $impressions=(int) $assoc["Impressions"];
            $rev=BigDecimal::of($assoc["Media Spend (USD)"])->toScale(2, RoundingMode::DOWN);
            if($impressions>0 && $rev->compareTo(BigDecimal::zero()) > 0 && $date>=CarbonImmutable::parse('2025-09-01')){
                $result->push(new self(
                        date: $date,
                        domain: $domain,
                        ad_unit: $adunit,
                        inventory: $inventory,
                        impressions: $impressions,
                        revenue: $rev,
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
        return "BidsPlaced"; // Placeholder
    }

    public function getTableName(): string
    {
        return "ssp_ttd";
    }

    public function getCsvDelimiter(): string
    {

        return ","; // Placeholder
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
                    'TTD' AS ssp,
                    domain,
                    date,
                    inventory AS inventory_calc,
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
            $a=(ImportersHelper::convertUsdToEur(BigDecimal::of($item->revenue), $current_date));
            $item->revenue = $a;
        }

        return [
            'domains' => $domainList,
            'deals' => [],
        ];
    }
}
