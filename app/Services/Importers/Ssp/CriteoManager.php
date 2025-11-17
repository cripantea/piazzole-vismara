<?php
namespace App\Services\Importers\Ssp;

use App\Models\SspAniview;
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
use Modules\Importer\DTOs\AniviewDTO;
use App\Services\Importers\Parsers\CsvParser;
use App\Services\Importers\Parsers\JsonParser;

class CriteoManager extends JsonParser implements SspDownloadInterface, CacheBuilderInterface
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

        $apiKey = config('ssp.criteo');

        $startDate = $fromDate ?? Carbon::now()->subDays(7)->format('Y-m-d');
        $endDate = $toDate ?? Carbon::now()->format('Y-m-d');
        $url = "https://pmc.criteo.com/api/stats?apitoken={$apiKey}&dimensions=domain,ZoneId,ZoneName&metrics=Clicks,Revenue,CriteoDisplays&begindate={$startDate}&enddate={$endDate}";

        try {
            $response = Http::get($url);

            if ($response->successful()) {
                $responseString = $response->body();

                $filePath = $fileName ?? SspReader::generateOutFileName("criteo", "json", $startDate, $endDate);
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
        foreach ($input as $row)
        {
            if($row['CriteoDisplays']==0) continue;
            $result->push(new self(
                CarbonImmutable::parse($row['TimeId'] ?? now()),
                $row['Domain'] ?? '',
                trim($row['ZoneName'] ?? '')."_".$row['ZoneId'],
                'DISPLAY',
                (int)($row['CriteoDisplays'] ?? 0),
                (int) $row['Clicks'],
                (BigDecimal::of(str_replace(",", ".", $row['Revenue'])))
            ));
        }

        $remnant = $result->filter(function ($item) {
            return strtolower($item->domain) === 'remnant';
        });

        $nonRemnant = $result->filter(function ($item) {
            return strtolower($item->domain) !== 'remnant';
        });


        foreach ($remnant as $item) {
            $adUnits = $nonRemnant->filter(function ($nonRemnantItem) use ($item) {
                return $nonRemnantItem->ad_unit === $item->ad_unit && $nonRemnantItem->date->isSameDay($item->date);
            });

            if ($adUnits->isNotEmpty()) {
                $firstAdUnit = $adUnits->first();

                $firstAdUnit->revenue = $firstAdUnit->revenue->plus($item->revenue);
                $firstAdUnit->clicks += $item->clicks;
                $firstAdUnit->impressions += $item->impressions;
            } else {
            }
        }

        return $nonRemnant;
    }

    public function getDelimiter(): string{
        return "";
    }

    public function getTableName(): string{
        return "ssp_criteo";
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
                    'CRITEO' AS ssp,
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

        return [
            'domains' => $domainList,
            'deals' => [],
        ];

    }
}
