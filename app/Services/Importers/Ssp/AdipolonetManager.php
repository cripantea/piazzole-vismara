<?php

namespace App\Services\Importers\Ssp;

use Carbon\CarbonImmutable;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use App\Services\Importers\Parsers\CsvParser;
use App\Services\Importers\Interfaces\CacheBuilderInterface;

class AdipolonetManager extends AdipoloManager implements CacheBuilderInterface
{
     public function getReportCode(): string
    {
        return "net";
    }
   public function __construct(
        ?CarbonImmutable $date = null,
        ?string $domain = null,
        ?string $ad_unit = null,
        ?string $report_code = null,
        ?int $impressions = null,
        ?int $clicks = null,
        ?BigDecimal $revenue = null,
    ) {
        parent::__construct($date, $domain, $ad_unit, $report_code, $impressions, $clicks, $revenue);
    }

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
            $a=count($header);
            $b=count($riga);
            if($a != $b) {
                continue;
            }
            $assoc = array_combine($header, $riga);
            if (! $assoc) {
                continue;

            }
            if (((int)(((int) ($assoc['Impression']))*0.9))==0 ){
                continue;
            }
            $result->push(new self(
                date: CarbonImmutable::parse($assoc['Date']),
                domain: $assoc['Grouped Domain'],
                ad_unit: (str_starts_with(strtolower($assoc['Grouped Domain']), 'amp') || str_starts_with(strtolower($assoc['Grouped Domain']), 'video-'))
                            ? 'ADIPOLO_TAG_VIDEO_AMP'
                            : 'ADIPOLO_TAG_VIDEO',
                report_code: $this->getReportCode(),
                impressions: (int) ($assoc['Impression']*0.9),
                clicks: 0,
                revenue: BigDecimal::of(str_replace(',', '.', $assoc['Total Cost']))->multipliedBy(0.9)
            ));


        }

        return $result;
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

    // Dummy implementation con errore intenzionale
    public function getDomainCache(?\Carbon\CarbonImmutable $current_date): array
    {
        $errore = $variabileNonDefinita + 1; // errore intenzionale
        return [
            'domains' => [],
            'deals' => [],
        ];
    }
}
