<?php

namespace App\Services\Importers\Ssp;

use App\Services\Importers\Interfaces\CacheBuilderInterface;
use Carbon\CarbonImmutable;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use App\Services\Importers\Parsers\CsvParser;
use App\Services\Importers\Helpers\ImportersHelper;
use Illuminate\Support\Facades\DB;
class GoogleimpressionsdetailsManager extends CsvParser implements CacheBuilderInterface
{
    public function __construct(
        public ?CarbonImmutable $date = null,
        public ?string $domain = null,
        public ?string $inventory = null,
        public ?string $canale = null,
        public ?int $impressions = null,
        public ?BigDecimal $ecpm = null,
    ) {}

    public static $mapping = [
        'date' => 'Data',
        'domain' => 'Dominio',
        'inventory' => 'Inventory',
        'canale' => 'Canale',
        'impressions' => 'Impressioni',
        'ecpm' => 'eCPM',
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
                date: CarbonImmutable::createFromFormat('d/m/y', $assoc['Data'])->startOfDay(),
                domain: $assoc['Unità pubblicitaria'],
                inventory: (trim($riga[2]) == "Set creatività video") ? "VIDEO" : "DISPLAY",
                canale: $assoc['Canale di domanda'],
                impressions: (int) str_replace('.', '', $assoc['Impressioni fatturate']),
            ));
        }
        $dati = $result
            ->groupBy(fn($x) => $x->date . '|' . $x->domain . '|' . $x->inventory . '|' . $x->canale)
            ->map(function ($group) {
                $first = $group->first();
                return (object)[
                    'date' => $first->date,
                    'domain' => $first->domain,
                    'inventory' => $first->inventory,
                    'canale' => $first->canale,
                    'impressions' => $group->sum('impressions'),
                ];
            })
            ->values();

            $dates = $dati->pluck('date')->map(fn($d) => $d instanceof \Carbon\CarbonImmutable ? $d->toDateString() : $d)->unique()->values();

            $availableDates = collect(
                DB::connection('alternate')->table('ssp_google_impressions')
                    ->whereIn('date', $dates)
                    ->get()
            );

            $toAdd = collect();

            $toAdd = $dati
                ->where('inventory', 'DISPLAY')
                ->where('canale', 'Ad server')
                ->map(function ($item) use ($availableDates) {
                    $theDate = $availableDates->firstWhere('date', $item->date->toDateString());

                    if ($theDate) {

// echo $item->date."-".$item->domain."\n";
// echo $theDate->inevase. "-". $theDate->evase."\n";
// echo $theDate->inevase/$theDate->evase."\n";
// echo $item->impressions."\n";

                        $a=BigDecimal::of($theDate->inevase)->dividedBy($theDate->evase, 12, RoundingMode::DOWN)
                            ->multipliedBy($item->impressions)
                            ;
// var_dump($a);
                        $imp=$a->toScale(0, \Brick\Math\RoundingMode::DOWN)
                                ->toInt();
                        $a=(int)(($theDate->inevase/$theDate->evase)*$item->impressions);

                        return (object)[
                            'date' => $item->date,
                            'domain' => $item->domain,
                            'inventory' => $item->inventory,
                            'canale' => 'Ad server inevase',
                            'impressions' => $a
                                ,
                          ];
                    }

                    return null;
                })
                ->filter();

            $dati = $dati->merge($toAdd);

            $ecpmsDisplay = ImportersHelper::GetEcpms("storico_ecpm_google_display", $dati->pluck('date'));
            $ecpmsVideo = ImportersHelper::GetEcpms("storico_ecpm_google_video", $dati->pluck('date'));

            $firstMay = CarbonImmutable::create(2021, 5, 1);

    // Assign ecpm for display
    foreach ($dati->where('inventory', 'DISPLAY') as $item) {
        if ($item->date < $firstMay && ! str_starts_with($item->canale, "Ad server")) {
            continue;
        }

        $ecpm = collect($ecpmsDisplay)->first(function ($x) use ($item) {
            return CarbonImmutable::parse($x->data)->month == $item->date->month &&
                   CarbonImmutable::parse($x->data)->year == $item->date->year;
        });

        $item->ecpm = BigDecimal::of($ecpm->valore) ?? null;
    }

    // Assign ecpm for video
    foreach ($dati->where('inventory', 'VIDEO')->where('canale', 'Ad server') as $item) {
        if ($item->date < $firstMay && ! str_starts_with($item->canale, "Ad server")) {
            continue;
        }

        $ecpm = collect($ecpmsVideo)->first(function ($x) use ($item) {
            return CarbonImmutable::parse($x->data)->month == $item->date->month &&
                   CarbonImmutable::parse($x->data)->year == $item->date->year;
        });

        $item->ecpm = BigDecimal::of($ecpm->valore) ?? null;
    }

    return $dati;
        //return $result;
    }

    public function getDelimiter(): string
    {
        return "Canale di domanda"; // Placeholder
    }

    public function getEndDelimiter(): string
    {
        return "Totale"; // Placeholder
    }

    public function getTableName(): string
    {
        return "ssp_google_impressions_details";
    }

    public function getCsvDelimiter(): string
    {
        // --- MARKER: CUSTOM CSV DELIMITER LOGIC REQUIRED HERE ---
        // This method should return the CSV field delimiter (e.g., ",", "\t", ";").
        // Example: return "\t"; // For tab-separated values
        // Example: return ","; // For comma-separated values
        // --- END MARKER ---
        return ","; // Placeholder
    }

    public function getDomainCache(?CarbonImmutable $current_date): array
    {
        $domainQueryString = "
                SELECT
                     ssp,
                     domain,
                     date,
                     inventory_calc AS inventory,
                     iddeal_calc AS id_deal,
                     requests,
                     impressions,
                     clicks,
                     revenue
                 FROM (
                     SELECT
                     'ADMANAGER' AS ssp,
                     domain,
                     date,
                     inventory as inventory_calc,
                     'FEE' as iddeal_calc ,
                     0 as requests,
                     0 as impressions,
                     0 as clicks,
                     sum(-1 * FLOOR(impressions * ecpm / 10) / 100) as revenue
                FROM " . $this->getTableName() . "
                WHERE date = '" . $current_date->toDateString() . "'
                GROUP BY domain, date, inventory_calc, iddeal_calc
            ) calc";

        $domainQueryResult = DB::connection('alternate')->select($domainQueryString);

        $domainQueryCollection = collect($domainQueryResult);

        $domainList = $domainQueryCollection->toArray();
        foreach ($domainList as $item) {
            $a=BigDecimal::of($item->revenue);
            $item->revenue = $a;
        }


        return [
            'domains' => $domainList,
            'deals' => [],
        ];
    }
}
