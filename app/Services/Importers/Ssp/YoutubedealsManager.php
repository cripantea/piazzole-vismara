<?php

namespace App\Services\Importers\Ssp;

use Carbon\CarbonImmutable;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use App\Services\Importers\Parsers\CsvParser;
use App\Services\Importers\Helpers\ImportersHelper;
use App\Services\Importers\Interfaces\CacheBuilderInterface;

class YoutubedealsManager extends CsvParser implements CacheBuilderInterface
{
    public function __construct(
        public ?CarbonImmutable $date = null,
        public ?string $channel = null,
        public ?string $id_deal = null,
        public ?string $deal_name = null,
        public ?BigDecimal $revenue = null,
    ) {}

    public static $mapping = [
        'date' => 'Data',
        'channel' => 'Canale',
        'id_deal' => 'IdDeal',
        'deal_name' => 'DealName',
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
                date: CarbonImmutable::createFromFormat('d/m/y', $assoc['Data']),
                channel: $assoc['yt_channel_id'],
                id_deal: 'DEAL_'.trim($assoc['ID ordine']),
                deal_name: trim($assoc['Ordine']),
                revenue: BigDecimal::of(str_replace(',', '.', $assoc['Entrate CPC e CPM totali (€)'])),
            ));

        }

        return $result;
    }

    public function getDelimiter(): string
    {

        return "yt_channel_id"; // Placeholder
    }

    public function getEndDelimiter(): string
    {

        return "Totale,"; // Placeholder
    }

    public function getTableName(): string
    {
        return "ssp_youtube_deals_importati";
    }

    public function getCsvDelimiter(): string
    {
        return ","; // Placeholder
    }

    // Dummy implementation con errore intenzionale
    public function getDomainCache(?\Carbon\CarbonImmutable $current_date): array
    {
        $errore = $pippo; // errore intenzionale
        return [
            'domains' => [],
            'deals' => [],
        ];
    }
}
