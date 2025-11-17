<?php

namespace App\Services\Importers\Ssp;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use App\Services\Importers\Parsers\CsvParser;

class GoogleimpressionsManager extends CsvParser
{
    public function __construct(
        public ?CarbonImmutable $date = null,
        public ?int $inevase = null,
        public ?int $evase = null,
    ) {}

    public static $mapping = [
        'date' => 'Data',
        'inevase' => 'Inevase',
        'evase' => 'Evase',
    ];

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


            $result->push(new self(
                date: CarbonImmutable::createFromFormat('d/m/y', $assoc['Data']),
                inevase: (int) str_replace('.', '', $assoc['Impressioni inevase fatturate']),
                evase: (int) str_replace('.', '',$assoc['Impressioni fatturate']),
            ));

        }

        return $result;
    }

    public function getDelimiter(): string
    {

        return "Impressioni inevase fatturate"; // Placeholder
    }

    public function getEndDelimiter(): string
    {

        return "Totale"; // Placeholder
    }

    public function getTableName(): string
    {
        return "ssp_google_impressions";
    }

    public function getCsvDelimiter(): string
    {

        return ","; // Placeholder
    }
}
