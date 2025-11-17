<?php

namespace App\Services\Importers\Ssp;

use App\Services\Importers\Interfaces\CacheBuilderInterface;
use Carbon\CarbonImmutable;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Services\Importers\Parsers\CsvParser;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

class GooglehistoricalManager extends CsvParser implements CacheBuilderInterface
{
    public function __construct(
        public ?CarbonImmutable $date = null,
        public ?string $domain = null,
        public ?string $ad_unit = null,
        public ?string $tag = null,
        public ?string $categoriadispositivo = null,
        public ?string $tipobranding = null,
        public ?string $id_deal = null,
        public ?string $dimensioniinventario = null,
        public ?int $requests = null,
        public ?int $matches = null,
        public ?BigDecimal $copertura = null,
        public ?int $clicks = null,
        public ?BigDecimal $ctrrichiesteannunci = null,
        public ?BigDecimal $ctr = null,
        public ?BigDecimal $ctrannuncio = null,
        public ?BigDecimal $cpc_eur = null,
        public ?BigDecimal $ecpm_richiesteannunci_eur = null,
        public ?BigDecimal $ecpm_corrispondenza_eur = null,
        public ?BigDecimal $incremento = null,
        public ?BigDecimal $revenue = null,
        public ?int $impressions = null,
        public ?BigDecimal $ecpm_annuncio_eur = null,
        public ?BigDecimal $visualizzazioneattivamisurabile = null,
        public ?BigDecimal $visualizzazioneattivavisibile = null,
        public ?int $impressioniattivateconvisualizzazioneattiva = null,
        public ?int $impressionimisurateconvisualizzazioneattiva = null,
        public ?int $impressionivisualizzateconvisualizzazioneattiva = null,
        public ?BigDecimal $tempovisibilitamedio = null,
    ) {}

    public static $mapping = [
        'date' => 'Data',
        'domain' => 'Dominio',
        'ad_unit' => 'AdUnit',
        'tag' => 'Tag',
        'categoriadispositivo' => 'CategoriaDispositivo',
        'tipobranding' => 'TipoBranding',
        'id_deal' => 'IdDeal',
        'dimensioniinventario' => 'DimensioniInventario',
        'requests' => 'Richieste',
        'matches' => 'Matches',
        'copertura' => 'Copertura',
        'clicks' => 'Clicks',
        'ctrrichiesteannunci' => 'CTRRichiesteAnnunci',
        'ctr' => 'CTR',
        'ctrannuncio' => 'CTRAnnuncio',
        'cpc_eur' => 'CPC_EUR',
        'ecpm_richiesteannunci_eur' => 'ECPM_RichiesteAnnunci_EUR',
        'ecpm_corrispondenza_eur' => 'ECPM_Corrispondenza_EUR',
        'incremento' => 'Incremento',
        'revenue' => 'EntrateStimate',
        'impressions' => 'Impressioni',
        'ecpm_annuncio_eur' => 'ECPM_Annuncio_EUR',
        'visualizzazioneattivamisurabile' => 'VisualizzazioneAttivaMisurabile',
        'visualizzazioneattivavisibile' => 'VisualizzazioneAttivaVisibile',
        'impressioniattivateconvisualizzazioneattiva' => 'ImpressioniAttivateConVisualizzazioneAttiva',
        'impressionimisurateconvisualizzazioneattiva' => 'ImpressioniMisurateConVisualizzazioneAttiva',
        'impressionivisualizzateconvisualizzazioneattiva' => 'ImpressioniVisualizzateConVisualizzazioneAttiva',
        'tempovisibilitamedio' => 'TempoVisibilitaMedio',
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
            $line=new self(
                date: CarbonImmutable::createFromFormat('d/m/y', $assoc['Data']) ,
                domain: strtolower(trim($assoc['Sito'])),
                ad_unit: $this->mapAdunitToOldName($assoc['Unità pubblicitaria']),
                tag: "",
                categoriadispositivo: $assoc['Categoria del dispositivo'],
                tipobranding: $assoc['Tipo di branding'],
                id_deal: $assoc['ID deal']==="0" ? "Open auction" : $assoc['ID deal'],
                dimensioniinventario: $assoc['Dimensioni annuncio richieste'],
                requests: (int) str_replace(',', '.', str_replace('.', '', str_replace('%', '', $assoc['Richieste di annunci Ad Exchange']))),
                matches: (int) str_replace(',', '.', str_replace('.', '', str_replace('%', '', $assoc['Risposte Ad Exchange pubblicate']))),
                copertura: BigDecimal::zero(),
                clicks: (int) str_replace(',', '.', str_replace('.', '', str_replace('%', '', $assoc['Clic Ad Exchange']))),
                ctrrichiesteannunci: BigDecimal::zero(),
                ctr: BigDecimal::zero(),
                ctrannuncio: BigDecimal::zero(),
                cpc_eur: BigDecimal::zero(),
                ecpm_richiesteannunci_eur: BigDecimal::zero(),
                ecpm_corrispondenza_eur: BigDecimal::zero(),
                incremento: BigDecimal::zero(),
                revenue: BigDecimal::of(str_replace(',', '.', str_replace('.', '', str_replace('%', '',  $assoc['Entrate Ad Exchange (€)'])))),
                impressions: (int) str_replace(',', '.', str_replace('.', '', str_replace('%', '',  $assoc['Impressioni Ad Exchange']))),
                ecpm_annuncio_eur: BigDecimal::zero(),
                visualizzazioneattivamisurabile: BigDecimal::zero(),
                visualizzazioneattivavisibile: BigDecimal::of(str_replace(',', '.', str_replace('.', '', str_replace('%', '', $assoc['% di impressioni Ad Exchange visibili con Visualizzazione attiva'])))),
                impressioniattivateconvisualizzazioneattiva: (int) str_replace(',', '.', str_replace('.', '', str_replace('%', '',  $assoc['Impressioni Ad Exchange idonee per Visualizzazione attiva']))),
                impressionimisurateconvisualizzazioneattiva: (int) str_replace(',', '.', str_replace('.', '', str_replace('%', '',  $assoc['Impressioni Ad Exchange misurabili con Visualizzazione attiva']))),
                impressionivisualizzateconvisualizzazioneattiva: 0,
                tempovisibilitamedio: BigDecimal::zero(),
            );

            $may2023 = CarbonImmutable::create(2023, 5, 1);

            if ($line->date->greaterThanOrEqualTo($may2023)) {
                if (str_contains(strtolower($line->dimensioniinventario), 'v') || str_contains(strtolower($line->ad_unit), '_video')) {
                    $line->dimensioniinventario = "VIDEO";
                }
            }
            $result->push($line);

        }
        $sportItaliaAdUnits = $result->filter(function ($item) {
            return str_starts_with($item->ad_unit, "5966054 » Sportitalia") && !str_starts_with(strtolower($item->ad_unit), "5966054 » sportitalialive");
        })->each(function ($item) {
            $item->domain = "www.sportitalia.com";
        });

        // Gestione app 5966054 » Comingsoon_it » Comingsoon App
        $comingsoonAdUnits = $result->filter(function ($item) {
            return str_starts_with($item->ad_unit, "5966054 » Comingsoon_it");
        })->each(function ($item) {
            $item->domain = "www.comingsoon.it";
        });

        $otherUnits = $result->filter(function ($item) use ($sportItaliaAdUnits, $comingsoonAdUnits) {
            return !$sportItaliaAdUnits->contains($item) && !$comingsoonAdUnits->contains($item);
        });

        return $this->groupManually($otherUnits)
            ->merge($this->groupManually($sportItaliaAdUnits))
            ->merge($this->groupManually($comingsoonAdUnits));

    }



    public function writeToDB($datas, $dtoClass, $report_code='')
    {

        $reflection = new ReflectionClass($dtoClass);

        $className = strtolower(str_replace('Manager', '', $reflection->getShortName()));
        $tableName = $this->getTableName();
        logger('start writedb -> '.$tableName);

        $fields = $this->describe();

        $names = array_column($fields, 'name');
        $fieldList = implode(',', $names);

        $vals = implode(',', array_fill(0, count($fields), '?'));

        $dateField = collect($fields)
            ->firstWhere('is_date', true)['name'] ?? null;

        if ($dateField) {
            $minIntervalDate = $datas->min($dateField);
            $maxIntervalDate = $datas->max($dateField);

            $currentDate = $minIntervalDate;

            while ($currentDate <= $maxIntervalDate) {
                        $minDate=$currentDate;
                        $maxDate=$currentDate;

                        try {
                            DB::connection('alternate')->beginTransaction();
                            logger('start transaction');

                            // Build the base delete query
                            $deleteQuery = DB::connection('alternate')->table($tableName)->whereBetween($dateField, [$minDate->toDateString(), $maxDate->toDateString()]);

                            // Add report_code condition if applicable
                            if ($this->getReportCode() != '' || $report_code != '') {
                                $code = $report_code ?: $this->getReportCode();
                                $deleteQuery->where('report_code', $code);
                            }

                            $deleteQuery->delete();
                            logger('after delete');

                            // Prepare data for batch insert
                            $rowsToInsert = [];
                            $fieldNames = collect($fields)->pluck('name')->toArray();
                            $dailyData = $datas->where(function ($item) use ($currentDate) {
                                return $item->date->toDateString() === $currentDate->toDateString();
                            });

                            foreach ($dailyData as $row) {
                                $rowData = [];
                                foreach ($fieldNames as $propertyName) {
                                    $value = $row->$propertyName;
                                    $scale = ($propertyName == 'revenue') ? 2 : 4;

                                    if ($value instanceof CarbonImmutable) {
                                        $value = $value->toDateString();
                                    } elseif ($value instanceof BigDecimal) {
                                        $value = $value->toScale($scale, RoundingMode::HALF_UP)->jsonSerialize();
                                    } elseif (is_bool($value)) {
                                        $value = (int)$value;
                                    }

                                    $rowData[$propertyName] = $value;
                                }
                                $rowsToInsert[] = $rowData;
                            }

                            // Perform batch insert with chunking
                            $chunkSize = 1000;
                            $totalRows = count($rowsToInsert);
                            $insertedCount = 0;

                            foreach (array_chunk($rowsToInsert, $chunkSize) as $chunk) {
                                DB::connection('alternate')->table($tableName)->insert($chunk);
                                $insertedCount += count($chunk);
                                logger("Inserted chunk of " . count($chunk) . " rows. Total inserted: {$insertedCount} of {$totalRows}");
                            }

                            DB::connection('alternate')->commit();
                            logger('after commit');

                            //return 1;
                        } catch (\Exception $ex) {
                            logger($ex->getMessage());
                            DB::connection('alternate')->rollBack();

                            return 0;

                        }


                $currentDate = $currentDate->addDay();
            }
            return 1;
        } else {
            return 0;
        }
    }
    private function groupManually(Collection $dati): Collection
    {
        return $dati->groupBy(function ($item) {
            return $item->date . $item->domain . $item->ad_unit . $item->tag . $item->categoriadispositivo . $item->tipobranding . $item->id_deal . $item->dimensioniinventario;
        })->map(function ($group) {

            $first = $group->first();
            $visualizzazioneAttivaNumeratore = $group->reduce(
                fn($carry, $item) => $carry->plus(BigDecimal::of($item->impressions)->multipliedBy($item->visualizzazioneattivavisibile)),
                BigDecimal::of('0')
            );
            $totalImpressions = $group->sum('impressions');

            $visualizzazioneAttivaVisibile = ($totalImpressions === 0)
                ? BigDecimal::of('0')
                : $visualizzazioneAttivaNumeratore->dividedBy(BigDecimal::of($totalImpressions), 2, RoundingMode::HALF_UP);


            return new self(
                date: $first->date,
                domain: $first->domain,
                ad_unit: $first->ad_unit,
                tag: $first->tag,
                categoriadispositivo: $first->categoriadispositivo,
                tipobranding: $first->tipobranding,
                id_deal: $first->id_deal,
                dimensioniinventario: $first->dimensioniinventario,
                requests: $group->sum('requests'),
                matches: $group->sum('matches'),
                copertura: $group->reduce(
                            fn($carry, $item) => $carry->plus($item->copertura),
                            BigDecimal::of('0')
                        ),
                clicks: $group->sum('clicks'),
                ctrrichiesteannunci: BigDecimal::zero(),
                ctr: BigDecimal::zero(),
                ctrannuncio: BigDecimal::zero(),
                cpc_eur: BigDecimal::zero(),
                ecpm_richiesteannunci_eur: BigDecimal::zero(),
                ecpm_corrispondenza_eur: BigDecimal::zero(),
                incremento: BigDecimal::zero(),
                revenue: $group->reduce(
                            fn($carry, $item) => $carry->plus($item->revenue),
                            BigDecimal::of('0')
                        ),
                impressions: $group->sum('impressions'),
                ecpm_annuncio_eur: BigDecimal::zero(),
                visualizzazioneattivamisurabile: BigDecimal::zero(),
                visualizzazioneattivavisibile: $visualizzazioneAttivaVisibile,
                impressioniattivateconvisualizzazioneattiva: $group->sum('impressioniattivateconvisualizzazioneattiva'),
                impressionimisurateconvisualizzazioneattiva: $group->sum('impressionimisurateconvisualizzazioneattiva'),
                impressionivisualizzateconvisualizzazioneattiva: $group->sum('impressionivisualizzateconvisualizzazioneattiva'),
                tempovisibilitamedio: BigDecimal::zero(),
            );
        })->values();
    }

    private function mapAdunitToOldName(string $s): string
    {
        // Rimuove i numeri tra parentesi.
        $s = preg_replace('/\([0-9]+\)/', '', $s);

        // Costruisce la vecchia nomenclatura.
        $s = "5966054 » " . $s;
        $s = str_replace(" ", "", $s);
        $s = str_replace("»", " » ", $s);
        $s = trim($s);

        return $s;
    }
    public function getDelimiter(): string
    {

        return "Impressioni Ad Exchange"; // Placeholder
    }

    public function getEndDelimiter(): string
    {
        return "Totale,"; // Placeholder
    }

    public function getTableName(): string
    {
        return "ssp_data_google";
    }

    public function getCsvDelimiter(): string
    {
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
                    'ADX' AS ssp,
                    domain,
                    date,
                    IF(dimensioniinventario LIKE '%video%' OR dimensioniinventario LIKE '%audio%', 'VIDEO', 'DISPLAY') as inventory_calc,
                    IF(id_deal LIKE '%open auction%', 'OPEN MARKET', id_deal) AS iddeal_calc,
                    sum(requests) as requests,
                    sum(impressions) as impressions,
                    sum(clicks) as clicks,
                    sum(revenue) as revenue
                FROM " . $this->getTableName() . "
                WHERE date = '" . $current_date->toDateString() . "'
                GROUP BY domain, date, inventory_calc, iddeal_calc
            ) calc";

        $domainQueryResult = DB::connection('alternate')->select($domainQueryString);

        $domainQueryCollection = collect($domainQueryResult);

        $idDealList = $domainQueryCollection
            ->where('id_deal', '!=', 'OPEN MARKET')
            ->pluck('id_deal')
            ->unique()
            ->values()
            ->all();

        $domainList = $domainQueryCollection->toArray();
        $dnList = [];

        if (count($idDealList) > 0) {
            // Load deals from DB
            $dealsOnDb = DB::connection('alternate')->table('deal')
                ->whereIn('id', $idDealList)
                ->where('ssp', 'ADX')
                ->get();

            foreach ($dealsOnDb as $deal) {
                if (!is_null($deal->dn)) {
                    foreach ($domainList as &$item) {
                        if ($item->id_deal == $deal->id) {
                            $valoreDn = BigDecimal::of($item->revenue)->multipliedBy('100')->multipliedBy(BigDecimal::of($deal->dn))->dividedBy('9')->toScale(2, RoundingMode::HALF_EVEN);
                            $dnList[] = [
                                'date' => $item->date,
                                'domain' => $item->domain,
                                'id_deal' => $item->id_deal,
                                'inventory' => $item->inventory,
                                'ssp' => $item->ssp,
                                'value' => $valoreDn
                            ];
                            $item->revenue = BigDecimal::of($item->revenue)->minus($valoreDn);
                        } else {
                            $item->revenue = BigDecimal::of($item->revenue);
                        }
                    }
                    unset($item); // break reference
                } else {
                    $domainList = array_filter($domainList, fn($x) => $x->id_deal != $deal->id);
                }
            }
        }


        return [
            'domains' => $domainList,
            'deals' => array_filter($dnList, fn($x) => $x['value']->compareTo(BigDecimal::zero()) > 0),
        ];
    }


}
