<?php

namespace App\Services\Importers\Ssp;

use App\Services\Importers\Helpers\ImportersHelper;
use App\Services\Importers\Interfaces\CacheBuilderInterface;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use App\Services\Importers\Parsers\CsvParser;
use Illuminate\Support\Facades\DB;


class SmartclipManager extends CsvParser implements CacheBuilderInterface
{
    public function __construct(
        public ?CarbonImmutable $date = null,
        public ?string $domain = null,
        public ?string $ad_unit = null,
        public ?string $device = null,
        public ?int $impressions = null,
        public ?int $clicks = null,
        public ?BigDecimal $ecpm = null,
        public ?BigDecimal $revenue = null,
    ) {}

    public static $mapping = [
        'date' => 'Data',
        'domain' => 'Dominio',
        'ad_unit' => 'AdUnit',
        'device' => 'Device',
        'impressions' => 'Impressioni',
        'clicks' => 'Clicks',
        'ecpm' => 'eCPM',
        'revenue' => 'EntrateStimate',
    ];
                // Map(x => x.Site).Name("Site & Apps").Index(0);
                // Map(x => x.Date).Name("Daily").Index(1);
                // Map(x => x.Device).Name("Device Groups").Index(2);
                // Map(x => x.Impressions).Name("Impressions").Index(3);
                // Map(x => x.Clicks).Name("Clicks").Index(4);


                        // Data = row.Date.ToDate("yyyy-MM-dd"),
                        // Dominio = CalcolaDominio(row.Site),
                        // Device = row.Device.Trim(),
                        // AdUnit = GetAdUnitType(row.Site),
                        // Impressioni = (int)(row.Impressions.ToInt()),
                        // Clicks = row.Clicks.ToInt(),
                        // eCPM = ecpms[row.Date.ToDate("yyyy-MM-dd").ToString("yyyy-MM")],

    public function convert(array $parsed): Collection
    {
        $header = $parsed['header'];
        $rows = $parsed['rows'];
        $ecpms=$this->getMonthlyEcpms();
        $result = collect();
        foreach ($rows as $riga) {
            $assoc = array_combine($header, $riga);
            if (! $assoc) {
                continue;
            }

            $tmpDate=CarbonImmutable::parse($assoc['Daily']);
            $result->push(new self(
                date: $tmpDate,
                domain: $this->CalcolaDominio($assoc['Site & Apps']),
                ad_unit: $this->GetAdUnitType($assoc['Site & Apps']),
                device: trim($assoc['Device Groups']),
                impressions: (int) $assoc['Impressions'],
                clicks: (int) $assoc['Clicks'],
                ecpm: BigDecimal::of($ecpms[$tmpDate->format('Y-m')]),
                revenue: BigDecimal::of($ecpms[$tmpDate->format('Y-m')])->multipliedBy((int) $assoc['Impressions'])->dividedBy(1000, 2, \Brick\Math\RoundingMode::HALF_EVEN),
            ));

        }
        $grouped = $result->groupBy(fn($item) => $item->date->format('Y-m-d') . '|' . $item->domain . '|' . $item->device . '|' . $item->ad_unit)
                ->map(function ($items) {
                    $first = $items->first();

                    return new self(
                        date: $first->date,
                        domain: $first->domain,
                        device: $first->device,
                        ad_unit: $first->ad_unit,
                        ecpm: $first->ecpm,
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

    public function getMonthlyEcpms(): array
    {
        $ecpms = [];
        $today = CarbonImmutable::now();

        // Loop for the last 24 months
        for ($i = 0; $i < 24; $i++) {
            // Calculate the date for the current iteration (month and year)
            $date = $today->copy()->subMonths($i);
            $month = $date->month;
            $year = $date->year;

            // Use the DB facade to execute a raw SQL query.
            // The first query in the subquery gets the ecpm value for the first day of the month.
            $ecpmData = DB::connection('alternate')->select(
                "SELECT ecpm FROM " . $this->getTableName() . " WHERE DAY(date) = 1 AND MONTH(date) = ? AND YEAR(date) = ? ORDER BY date ASC LIMIT 1",
                [$month, $year]
            );

            // Get the first result, or null if no data is found.
            $ecpm = $ecpmData[0]->ecpm ?? null;

            // If ECPM is null or less than or equal to 0.1, set it to 1.5
            if ($ecpm === null || $ecpm <= 0.1) {
                $ecpm = 1.5;
            }

            // Add the formatted date and ECPM to the results array
            $ecpms[$date->format('Y-m')] = $ecpm;
        }

        return $ecpms;
    }

    private function CalcolaDominio(string $s): string
    {
        if (str_contains($s, '_HTML5')) {
            $s = substr($s, 0, strpos($s, '_HTML5'));
        } elseif (str_contains($s, 'HTML5')) {
            $s = substr($s, 0, strpos($s, 'HTML5'));
        }

        if (str_contains($s, ' AMP')) {
            $s = str_replace(' AMP', '', $s);
        }

        $s = str_replace(['Intxt', 'B4U', 'InPage', 'HTML'], '', $s);
        return trim($s);
    }

    private function GetAdUnitType(string $s): string
    {
        $lowercaseS = strtolower($s);

        if (str_contains($lowercaseS, ' amp') || str_contains($lowercaseS, 'network amp')) {
            return "SMARTCLIP_TAG_VIDEO_AMP";
        }

        return "SMARTCLIP_TAG_VIDEO";
    }


    public function getDelimiter(): string
    {

        return "Site & Apps";
    }

    public function getTableName(): string
    {
        return "ssp_smartclip";
    }

    public function getCsvDelimiter(): string
    {

        return ","; // Placeholder
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
            FROM(
                SELECT
                        'SMARTCLIP' as ssp,
                            date,
                            domain,
                            'VIDEO' AS inventory_calc,
                            'OPEN MARKET' AS idDeal_calc,
                            0 AS  requests ,
                            SUM(impressions) AS impressions,
                            SUM(clicks) AS clicks,
                            SUM(revenue) AS  revenue
                FROM " . $this->getTableName() . "
                WHERE date = '" . $current_date->toDateString() . "'
            GROUP BY SSP , date , domain, inventory_calc, idDeal_calc) calc";



        $domainQueryResult = DB::connection('alternate')->select($domainQueryString);

        $domainQueryCollection = collect($domainQueryResult);

        $domainList = $domainQueryCollection->toArray();
        foreach ($domainList as $item) {
            $a=BigDecimal::of($item->revenue)->multipliedBy('0.96')->toScale(2, \Brick\Math\RoundingMode::DOWN);
            $item->revenue = $a;
        }


        return [
            'domains' => $domainList,
            'deals' => [],
        ];
    }
}
