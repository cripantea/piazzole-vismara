<?php

namespace App\Services\Importers\Ssp;

use App\Services\Importers\Helpers\ImportersHelper;
use App\Services\Importers\Interfaces\CacheBuilderInterface;
use Carbon\CarbonImmutable;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\Importers\Parsers\CsvParser;
use App\Services\Importers\Parsers\ExcelParser;

class ConfiantManager extends ExcelParser implements CacheBuilderInterface
{
    public function __construct(
        public ?CarbonImmutable $date = null,
        public ?string $domain = null,
        public ?int $impressions = null,
        public ?BigDecimal $ecpm = null,
    ) {}

    public static $mapping = [
        'date' => 'Data',
        'domain' => 'Dominio',
        'impressions' => 'Impressioni',
        'ecpm' => 'eCPM',
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

        $header = $output->first()->toArray();
        $rows = $output->slice(1)->values()->toArray(); // Skip first row, reindex

        return [
            'header' => $header,
            'rows' => $rows,
        ];
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
            $rowDate=CarbonImmutable::create(1899, 12, 30)->addDays((int) $assoc['partition_date']);
            $filterDate=($rowDate->format('Y-m')).'-01';
            $rowEcpm=DB::connection('alternate')->select("select valore from storico_ecpm_confiant where data like '{$filterDate}'");

            $result->push(new self(
                date: CarbonImmutable::create(1899, 12, 30)->addDays((int) $assoc['partition_date']),
                domain: $assoc['referrer'],
                impressions: (int) $assoc['impressions'],
                ecpm: BigDecimal::of($rowEcpm?$rowEcpm[0]->valore:0),
            ));

        }

        return $result;
    }

    public function getDelimiter(): string
    {
        return ""; // Placeholder
    }

    public function getTableName(): string
    {
        return "ssp_confiant";
    }

    public function getCsvDelimiter(): string
    {
        return ""; // Placeholder
    }

    public function getDomainCache(?CarbonImmutable $current_date): array
    {
        $domains=ImportersHelper::GetUsdEcpmCostList($current_date, $this->getTableName(), "DISPLAY");
        return [
            'domains' => $domains,
            'deals' => []
        ];
    }
}
