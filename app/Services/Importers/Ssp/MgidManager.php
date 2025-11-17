<?php

namespace App\Services\Importers\Ssp;

use App\Services\Importers\Interfaces\CacheBuilderInterface;
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

class MgidManager extends ExcelParser implements CacheBuilderInterface
{
    public function __construct(
        public ?CarbonImmutable $date = null,
        public ?string $domain = null,
        public ?int $impressions = null,
        public ?BigDecimal $revenue = null,
    ) {}

    public static $mapping = [
        'date' => 'Data',
        'domain' => 'Dominio',
        'impressions' => 'Impressioni',
        'revenue' => 'Entrate',
    ];
    public function parse(string $filename): array
    {
        $output = Excel::toCollection(null, $filename)->first();
        if ($output->isEmpty()) {
            return [
                'header' => [],
                'rows' => [],
            ];
        }

        $startParsing = false;
        $startIndex=null;
        foreach ($output as $rowIndex => $row) {
            if ($rowIndex === 0 || empty($row)) {
                continue;
            }
            if (strtolower(trim($row[2]))==="ad_requests") {
                $header=$row;
                $startIndex=$rowIndex;
                break;
            }
        }

        $header = $output[$startIndex]->toArray();
        $rows = array_slice($output->toArray(), $startIndex + 1);

        return [
            'header' => $header,
            'rows' => $rows,
        ];

    }


    public function convert(array $parsed): Collection
    {

        $header = $parsed['header'];
        $rows = $parsed['rows'];

        $result=collect();
        foreach ($rows as $riga)
        {
            $assoc = array_combine($header, $riga);
            if (! $assoc) {
                continue;
            }
            if(floatval($assoc['revenue'])<=0) continue;
            $result->push(new self(
                date: CarbonImmutable::parse( $assoc['date']),
                domain: str_replace("hb.", "", $assoc['domain']),
                impressions: (int) ($assoc['impressions'] ?? 0),
                revenue: BigDecimal::of($assoc['revenue'])
            ));
        }
        return $result;
    }

    public function getDelimiter(): string
    {
        return "";
    }

    public function getTableName(): string
    {
        return "ssp_mgid";
    }

    public function getCsvDelimiter(): string
    {
        return "";
    }

    public function getDomainCache(?CarbonImmutable $current_date): array
    {
        // TODO: Implement getDomainCache() method.
    }
}
