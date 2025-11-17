<?php

namespace App\Services\Importers\Ssp;

use App\Services\Importers\Helpers\ImportersHelper;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Brick\Math\BigDecimal;
use Carbon\CarbonPeriodImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\Importers\Parsers\CsvParser;
use App\Services\Importers\Parsers\ExcelParser;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Services\Importers\Interfaces\CacheBuilderInterface;

class MsnManager extends ExcelParser implements CacheBuilderInterface
{
    public function __construct(
        public ?CarbonImmutable $date = null,
        public ?string $domain = null,
        public ?BigDecimal $revenue = null,
    ) {}

    public static $mapping = [
        'date' => 'Data',
        'domain' => 'Dominio',
        'revenue' => 'Entrate',
    ];
    public function parse(string $filename): array
    {

        $allowedSheets = [
            'Display Brands & Country',
            'Native Brands & Country',
            'Embedded Video Brands & Country',
            'Watch Video Brands & Country',
            'Adjustment Details',
        ];

        $sheets = Excel::toCollection(null, $filename);

        if ($sheets->isEmpty()) {
            return [];
        }

        if (preg_match('/msn_(\d{4})_(\d{2})\.xlsx$/', basename($filename), $matches)) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
        }

        // 1. Extract FX rate from first sheet
        $fxRate = 1.0;
        $firstSheet=$sheets->first();
        foreach ($firstSheet as $row) {
            $label=strtolower(trim((string)$row->get(1)));
            if ($label === 'fx rate') {
                $fxRate = BigDecimal::of(str_replace(',', '.', $row->get(2)));
                break;
            }
        }

        $today = CarbonImmutable::today();
        if($today->month == $month)
        {
            $firstDay=$today->firstOfMonth();
            $lastDay=$today->subDay();
            $days=(int)$firstDay->diffInDays($lastDay)+1;
        } else {
            $firstDay=CarbonImmutable::createMidnightDate($year, $month, 1);
            $lastDay=$firstDay->endOfMonth();
            $days=(int)$firstDay->diffInDays($lastDay)+1;
        }

        $results = [];

        // 2. Process all sheets
        $maxSheets=count($sheets)-1;

        $spreadsheet = IOFactory::load($filename);


        foreach ($spreadsheet->getSheetNames() as $index => $name) {
            if (!in_array($name, $allowedSheets)) continue;

            $sheet = $spreadsheet->getSheet($index);
            $data = $sheet->toArray();

            $startParsing = false;

            $highestRow = $sheet->getHighestRow();
            $highestColumn = $sheet->getHighestColumn();

            for ($rowIndex = 1; $rowIndex <= $highestRow; $rowIndex++) {
                // Get cell values directly
                $firstCell = $sheet->getCell('B' . $rowIndex); // 'Company Legal Name' is in column B

                if (!$startParsing) {
                    if (strtolower(trim((string)$firstCell->getValue())) === "company legal name") {
                        $startParsing = true;
                    }
                    continue;
                }

                $domainCell = $sheet->getCell('C' . $rowIndex); // 'Dominio' is in column C
                $revenueCell = $sheet->getCell('H' . $rowIndex); // 'Entrate' is in column H

                // Get the raw value using getValue()
                $domain = strtolower(trim((string)$domainCell->getValue()));
                $revenueValue = $revenueCell->getCalculatedValue(); // Use getCalculatedValue for formulas

                // You no longer need to use preg_replace as you are getting a float
                $value = (float) $revenueValue;
                $cents = intval(floor($value * 100)); // Still truncate

                if (!isset($results[$domain])) {
                    $results[$domain] = 0;
                }
                $results[$domain] += $cents;
            }
            // foreach ($data as $rowIndex => $row) {
            //     // Skip header rows
            //     if ($rowIndex === 0 || empty($row)) {
            //         continue;
            //     }
            //     if (! $startParsing) {
            //         if (strtolower(trim($row[1]))==="company legal name") {
            //             $startParsing = true;
            //         }
            //         continue;
            //     }


            //     $domain = strtolower(trim($row[2]));
            //     if(!isset($results[$domain])){
            //         $results[$domain]=0;
            //     }
            //     $value = floatval(preg_replace('/[^\d.-]/', '', $row[7]));
            //     $cents = intval(floor($value * 100)); // always truncate
            //     $results[$domain] += $cents;

            // }
        }

        $output=[];
        foreach($results as $d=>$totalCents)
        {
            $converted = BigDecimal::of($totalCents)->dividedBy(100, 2, RoundingMode::HALF_UP)->dividedBy($fxRate, 10, RoundingMode::HALF_UP)->dividedBy($days, 2, RoundingMode::HALF_UP);

            foreach (CarbonPeriodImmutable::create($firstDay, $lastDay) as $date) {
                $output[]=[
                    'date' => $date,
                    'domain' => strtolower($d)=='ottopagine'?'ottopagine_msn':$d,
                    'revenue' =>$converted
                ];
            }
        }

        return $output;
                // return [
                //     'header' => $header,
                //     'rows' => $rows,
                // ];
    }


    public function convert(array $parsed): Collection
    {

        $result = collect();
        foreach ($parsed as $riga) {

            $result->push(new self(
                date: $riga['date'],
                domain: $riga['domain'],
                revenue: $riga['revenue'],
            ));

        }

        return $result;
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
        return "ssp_msn";
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
                    'MSN' AS ssp,
                    domain,
                    date,
                    'DISPLAY' AS inventory_calc,
                    'OPEN MARKET' AS iddeal_calc,
                    0 AS requests,
                    0 AS impressions,
                    0 AS clicks,
                    SUM(revenue) AS revenue
                FROM " . $this->getTableName() . "
                WHERE date = '" . $current_date->toDateString() . "'
                GROUP BY domain, date, inventory_calc, iddeal_calc
            ) calc";

        $domainQueryResult = DB::connection('alternate')->select($domainQueryString);

        $domainQueryCollection = collect($domainQueryResult);

        $domainList= $domainQueryCollection->toArray();

        $conversionDeadline = CarbonImmutable::create(2024, 12, 1);

        if ($current_date < $conversionDeadline) {
            foreach ($domainList as $item) {
                $a=(ImportersHelper::convertUsdToEur(BigDecimal::of($item->revenue), $current_date));
                $item->revenue = $a;
            }
        }

        return [
            'domains' => $domainList,
            'deals' => [],
        ];
    }
}
