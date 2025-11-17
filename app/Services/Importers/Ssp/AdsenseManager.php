<?php

namespace App\Services\Importers\Ssp;

use App\Models\SspAniview;
use App\Services\Importers\Interfaces\CacheBuilderInterface;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Importer\DTOs\AniviewDTO;
use App\Services\Importers\Interfaces\SspParserInterface;
use App\Services\Importers\Parsers\CsvParser;

class AdsenseManager extends CsvParser implements CacheBuilderInterface
{
    public function __construct(
        public ?CarbonImmutable $date=null,
        public ?string $domain=null,
        public ?int $impressions=null,
        public ?int $clicks=null,
        public ?BigDecimal $revenue=null,
    ){}


    public static $mapping = [
        'date' => 'Data',
        'domain' => 'Dominio',
        'impressions' => 'Impressioni',
        'clicks' => 'Clicks',
        'revenue' => 'Entrate',
    ];

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

            $result->push(new self(
                CarbonImmutable::parse( $assoc['Date']),
                $assoc['Site'],
                (int)$assoc['Impressions'],
                (int)$assoc['Clicks'],
                (BigDecimal::of(str_replace(",", ".", $assoc['Estimated earnings (EUR)']))),
            ));
        }
        return $result;
    }

    // public function specialConditions(Collection $datiFile): Collection
    // {
    //     return $datiFile;
    // }

    public function getDelimiter(): string{
        return "Page RPM";
    }

    public function getTableName(): string{
        return "ssp_adsense";
    }
    public function getCsvDelimiter(): string{
        return "\t";
    }

    public function getDomainCache(?CarbonImmutable $current_date): array
    {
        // TODO: Implement getDomainCache() method.
        echo $error;
    }
}
