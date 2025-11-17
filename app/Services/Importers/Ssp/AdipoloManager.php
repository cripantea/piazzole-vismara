<?php

namespace App\Services\Importers\Ssp;

use Carbon\CarbonImmutable;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use App\Services\Importers\Parsers\CsvParser;
use App\Services\Importers\Interfaces\CacheBuilderInterface;

class AdipoloManager extends CsvParser
{
    public function __construct(
        public ?CarbonImmutable $date = null,
        public ?string $domain = null,
        public ?string $ad_unit = null,
        public ?string $report_code = null,
        public ?int $impressions = null,
        public ?int $clicks = null,
        public ?BigDecimal $revenue = null,
    ) {}

    public static $mapping = [
        'date' => 'Data',
        'domain' => 'Dominio',
        'ad_unit' => 'AdUnit',
        'report_code' => 'ReportCode',
        'impressions' => 'Impressioni',
        'clicks' => 'Clicks',
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
                date: CarbonImmutable::parse($assoc['Date']),
                domain: $assoc['Grouped Domain'],
                ad_unit: (str_starts_with(strtolower($assoc['Grouped Domain']), 'amp') || str_starts_with(strtolower($assoc['Grouped Domain']), 'video-'))
                            ? 'ADIPOLO_TAG_VIDEO_AMP'
                            : 'ADIPOLO_TAG_VIDEO',
                report_code: $this->getReportCode(),
                impressions: (int) $assoc['Impression']*0.9,
                clicks: (int)($assoc['Clicks']??0),
                revenue: BigDecimal::of(str_replace(',', '.', $assoc['Estimated earnings (EUR)']))
            ));


        }

        return $result;
    }

    public function getReportCode(): string
    {
        return "";
    }

    public function getDelimiter(): string
    {
        return "Grouped Domain";
    }

    public function getTableName(): string
    {
        return "ssp_adipolo";
    }

    public function getCsvDelimiter(): string
    {
        return ",";
    }
}
