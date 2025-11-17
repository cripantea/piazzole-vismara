<?php

namespace App\Services\Importers\Ssp;

use App\Services\Importers\Interfaces\CacheBuilderInterface;
use Carbon\CarbonImmutable;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use App\Services\Importers\Parsers\CsvParser;
use Brick\Math\RoundingMode;

class ViralizeManager extends CsvParser implements CacheBuilderInterface

{
    public function __construct(
        public ?CarbonImmutable $date = null,
        public ?string $domain = null,
        public ?int $impressions = null,
        public ?int $clicks = null,
        public ?BigDecimal $vtr =null,
        public ?BigDecimal $revenue = null,
    ) {}

    public static $mapping = [
        'date' => 'Data',
        'domain' => 'Dominio',
        'impressions' => 'Impressioni',
        'clicks' => 'Clicks',
        'vtr' => 'VTR',
        'revenue' => 'EntrateStimate',
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
                date: CarbonImmutable::parse($assoc['Data e ora']),
                domain: $assoc['Sito'],
                impressions: (int) $assoc['Impressioni'],
                clicks: (int)($assoc['Click']??0),
                vtr: BigDecimal::of($assoc['Ad VTR 100%'])->toScale(2, RoundingMode::DOWN),
                revenue: BigDecimal::of($assoc['Revenue PB'])->toScale(2, RoundingMode::DOWN)
            ));


        }

        return $result;
    }

    public function getReportCode(): string
    {
        return "";
    }

    public function getEndDelimiter():string
    {
        return "Totals,";
    }

    public function getDelimiter(): string
    {
        return "Unit Richieste";
    }

    public function getTableName(): string
    {
        return "ssp_viralize";
    }

    public function getCsvDelimiter(): string
    {
        return ",";
    }

    public function getDomainCache(?CarbonImmutable $current_date): array
    {
        // TODO: Implement getDomainCache() method.
    }
}
