<?php

namespace App\Services\Importers\Ssp;

use Carbon\CarbonImmutable;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use App\Services\Importers\Parsers\CsvParser;
use App\Services\Importers\Helpers\ImportersHelper;

class ViewabilityvideoManager extends CsvParser
{
    public function __construct(
        public ?CarbonImmutable $date = null,
        public ?string $domain = null,
        public ?string $ad_unit = null,
        public ?int $impressions = null,
        public ?BigDecimal $viewability = null,
        public ?BigDecimal $fill_rate = null,
        public ?BigDecimal $total_fill_rate = null,
    ) {}

    public static $mapping = [
        'date' => 'Data',
        'domain' => 'Dominio',
        'ad_unit' => 'AdUnit',
        'impressions' => 'Impressioni',
        'viewability' => 'Viewability',
        'fill_rate' => 'Completamento',
        'total_fill_rate' => 'Riempimento',
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
                domain: ImportersHelper::getDomain($assoc['Unità pubblicitaria']),
                ad_unit: ImportersHelper::getAdunit($assoc['Unità pubblicitaria']),
                impressions: (int) str_replace('.', '', $assoc['Impressioni totali']),
                viewability: BigDecimal::of(str_replace(',', '.', str_replace('%', '', $assoc['% totale di impressioni visibili con Visualizzazione attiva']))),
                fill_rate: BigDecimal::of(str_replace(',', '.', str_replace('%', '', $assoc['Percentuale di completamento']))),
                total_fill_rate: BigDecimal::of(str_replace(',', '.', str_replace('%', '', $assoc['Tasso di riempimento totale']))),
            ));

        }

        return $result;
    }

    public function getDelimiter(): string
    {

        return "Tasso di riempimento totale";
    }

    public function getEndDelimiter(): string
    {

        return "Totale,";
    }

    public function getTableName(): string
    {
        return "ssp_viewability_video";
    }

    public function getCsvDelimiter(): string
    {
        return ",";
    }
}
