<?php

namespace App\Services\Importers\Ssp;

use App\Services\Importers\Interfaces\CacheBuilderInterface;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use App\Services\Importers\Parsers\CsvParser;

class AdsenseadunitsManager extends CsvParser implements CacheBuilderInterface
{
    public function __construct(
        public ?CarbonImmutable $date = null,
        public ?string $ad_unit = null,
        public ?int $impressions = null,
        public ?int $clicks = null,
        public ?BigDecimal $revenue = null
    ) {}

    public static $mapping = [
        'date' => 'Data',
        'ad_unit' => 'AdUnit',
        'impressions' => 'Impressioni',
        'clicks' => 'Clicks',
        'revenue' => 'Entrate',
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
                CarbonImmutable::parse($assoc['Date']),
                trim(mb_convert_encoding($assoc['Ad unit'], 'UTF-8', 'Windows-1252')),
                (int) $assoc['Impressions'],
                (int) $assoc['Clicks'],
                BigDecimal::of(str_replace(',', '.', $assoc['Estimated earnings (EUR)']))
            ));
        }

        return $result;
    }

    public function getDelimiter(): string {
        return "Impressions";
    }

    public function getTableName(): string {
        return "ssp_adsense_adunits";
    }

    public function getCsvDelimiter(): string {
        return "\t";
    }

    public function getDomainCache(?CarbonImmutable $current_date): array
    {
        echo $error;
        // TODO: Implement getDomainCache() method.
    }
}
