<?php

namespace App\Services\Importers\Ssp;

use App\Models\SspAniview;
use App\Services\Importers\Interfaces\CacheBuilderInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Importer\DTOs\AniviewDTO;
use App\Services\Importers\Interfaces\SspParserInterface;
use App\Services\Importers\Parsers\CsvParser;

class AniviewManager extends CsvParser implements CacheBuilderInterface
{
    public function __construct(
        public ?CarbonImmutable $date,
        public ?string $domain,
        public ?int $impressions,
        public ?int $inventory,
        public ?int $viewableImpressions,
        public ?int $complete
    ){}


    public static $mapping = [
        'date' => 'Data',
        'domain' => 'Dominio',
        'impressions' => 'Impressioni',
        'inventory' => 'Inventory',
        'viewableImpressions' => 'ViewableImpression',
        'complete' => 'Complete',
    ];

    public function convert(array $parsed): Collection
    {
        $header = $parsed['header'];
        $rows = $parsed['rows'];
        $result=collect();
        foreach ($rows as $riga)
        {
            logger(print_r($riga, true));
            if(count($riga) < 4){
                continue;
            }
            $assoc = array_combine($header, $riga);
            if (! $assoc) {
                continue;
            }
            $result->push(new self(
                date: CarbonImmutable::parse( $assoc['Date']),
                domain: $assoc['Grouped Domain'],
                impressions:(int)$assoc['Impression'],
                inventory:(int)$assoc['Inventory'],
                viewableImpressions:(int)$assoc['Viewable Impression'],
                complete:(int)$assoc['Complete']
            ));
        }
        return $result;
    }

    // public function specialConditions(Collection $datiFile): Collection
    // {
    //     return $datiFile;
    // }

    public function getDelimiter(): string{
        return "Grouped Domain";
    }

    public function getTableName(): string{
        return "ssp_aniview";
    }
    public function getCsvDelimiter(): string{
        return ",";
    }

    public function getDomainCache(?CarbonImmutable $current_date): array
    {
        // TODO: Implement getDomainCache() method.
    }
}
