<?php

namespace App\Services\Importers\Ssp;

use App\Models\SspAniview;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Importer\DTOs\AniviewDTO;
use App\Services\Importers\Interfaces\SspParserInterface;
use App\Services\Importers\Parsers\CsvParser;
use App\Services\Importers\Interfaces\CacheBuilderInterface;

class AdsenseformatiManager extends CsvParser implements CacheBuilderInterface
{
    public function __construct(
        public ?CarbonImmutable $date=null,
        public ?string $domain=null,
        public ?string $format=null,
        public ?int $requests=null,
        public ?int $impressions=null,
        public ?int $clicks=null,
        public ?BigDecimal $revenue=null,
    ){}


    public static $mapping = [
        'date' => 'Data',
        'domain' => 'Dominio',
        'format' => 'Formato',
        'requests' => 'Richieste',
        'impressions' => 'Impressioni',
        'clicks' => 'Click',
        'revenue' => 'Entrate',
    ];
    private function cleanFormato(AdsenseformatiManager $formato): AdsenseformatiManager
    {
        if (str_ends_with($formato->domain, '.cdn.ampproject.org')) {
            $formato->format .= ' AMP';

            $formato->domain = str_replace('.cdn.ampproject.org', '', $formato->domain);

            $formato->domain = str_replace('--', '*', $formato->domain);
            $formato->domain = str_replace('-', '.', $formato->domain);
            $formato->domain = str_replace('*', '-', $formato->domain);
        }

        // se inizia con www.
        if (str_starts_with($formato->domain, 'www.')) {
            $formato->domain = str_replace('www.', '', $formato->domain);
        }

        return $formato;
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
            $linea=new self(
                date: CarbonImmutable::parse( $assoc['Date']),
                domain: $assoc['Site'],
                format: $assoc['Ad format'],
                requests: (int) $assoc['Ad requests'],
                clicks:(int)$assoc['Clicks'],
                impressions:(int)$assoc['Impressions'],
                revenue: (BigDecimal::of(str_replace(",", ".", $assoc['Estimated earnings (EUR)']))),
            );

            $result->push($this->cleanFormato($linea));
        }

        $grouped = $result->groupBy(fn($item) => $item->date->format('Y-m-d') . '|' . $item->domain . '|' . $item->format)
                ->map(function ($items) {
                    $first = $items->first();

                    return new self(
                        date: $first->date,
                        domain: $first->domain,
                        format: $first->format,
                        requests: $items->sum(fn($i) => $i->requests),
                        impressions: $items->sum(fn($i) => $i->impressions),
                        clicks: $items->sum(fn($i) => $i->clicks),
                        revenue: $items->reduce(
                            fn($carry, $item) => $carry->plus($item->revenue),
                            BigDecimal::of('0')
                        )
                    );
                });

        return $grouped;
    }

    // public function specialConditions(Collection $datiFile): Collection
    // {
    //     return $datiFile;
    // }

    public function getDelimiter(): string{
        return "Ad format";
    }

    public function getTableName(): string{
        return "ssp_adsense_formati";
    }
    public function getCsvDelimiter(): string{
        return "\t";
    }

    // Dummy implementation con errore intenzionale
    public function getDomainCache(?\Carbon\CarbonImmutable $current_date): array
    {
        $errore = $erroreNonDefinito; // errore intenzionale
        return [
            'domains' => [],
            'deals' => [],
        ];
    }
}
