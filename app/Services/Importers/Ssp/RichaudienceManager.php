<?php
namespace App\Services\Importers\Ssp;

use App\Models\SspAniview;
use App\Services\Importers\Helpers\ImportersHelper;
use App\Services\Importers\Interfaces\CacheBuilderInterface;
use App\Services\SspReader;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode as MathRoundingMode;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\Importers\Interfaces\SspDownloadInterface;
use Modules\Importer\DTOs\AniviewDTO;
use App\Services\Importers\Parsers\CsvParser;
use App\Services\Importers\Parsers\JsonParser;

class RichaudienceManager extends JsonParser implements SspDownloadInterface, CacheBuilderInterface
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

        $apiKey = config('ssp.richaudience');

        $startDate = $fromDate ?? Carbon::now()->subDays(8)->format('Y-m-d');
        $endDate = $toDate ?? Carbon::now()->subDay()->format('Y-m-d');
        $url = "https://console.richaudience.com/api_/report/v1/main/get-report?token={$apiKey}&startDate={$startDate}&endDate={$endDate}&dimensions[]=date&dimensions[]=site&dimensions[]=placement&dimensions[]=data_type";

        try {
            $response = Http::get($url);

            if ($response->successful()) {
                $responseString = $response->body();

                $filePath = $fileName ?? SspReader::generateOutFileName("richaudience", "json", $startDate, $endDate);
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

    public function convert(array $input): Collection
    {

        $result = collect();
        foreach ($input['data'] as $row)
        {
            $a=BigDecimal::of(str_replace(",", ".", $row['revenue']))->toScale(2, MathRoundingMode::HALF_UP);
            //echo $row['revenue']."\n";
            if($a->compareTo(BigDecimal::zero())==0){
              //  echo "in continue";
                continue;
            } else {
                //echo "push"."\n";
                $result->push(new self(
                    date: CarbonImmutable::parse($row['date'] ?? now()),
                    domain: ($row['site'] ?? ''),
                    ad_unit: $row['placement'] ?? '',
                    inventory: (stripos($row['data_type'], 'video') !== false) ? 'VIDEO' : 'DISPLAY',
                    impressions: (int)($row['impressions'] ?? 0),
                    clicks: (int) $row['clicks'],
                    revenue: (BigDecimal::of(str_replace(",", ".", $row['revenue'])))
                ));
            }
        }

        return $result;
    }

    public function getDelimiter(): string{
        return "";
    }

    public function getCsvDelimiter(): string{
        return ",";
    }

    public function getTableName(): string{
        return "ssp_richaudience";
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
                     'RICHAUDIENCE' AS ssp,
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
