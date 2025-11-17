<?php

namespace App\Services\DataCache;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CreateDiretteCache
{
    public function __construct()
    {

    }

    public function handle(?string $dateFrom, ?string $dateTo): int
    {

        if (empty(trim($dateFrom))) {
            echo("Error: 'dateFrom' cannot be null or empty.");
            return 1;
        }

        if (empty(trim($dateTo))) {
            echo("Error: 'dateTo' cannot be null or empty.");
            return 1;
        }


        try {
            $carbonFrom = CarbonImmutable::createFromFormat('Y-m-d', $dateFrom);
            $carbonTo = CarbonImmutable::createFromFormat('Y-m-d', $dateTo);

        } catch (\Exception $e) {
            echo "Error: The provided date format is invalid. " . $e->getMessage();
            return 1;
        }

        if ($carbonFrom == null || $carbonTo == null) {
            echo("Error: 'dateFrom' or 'dateTo' is null.");
            return 2;
        }

        if ($carbonFrom->gt($carbonTo)) {
            echo("Error: 'dateFrom' cannot be after 'dateTo'.");
            return 3;
        }

        $currentDate = $carbonFrom;

        while ($currentDate->lte($carbonTo)) {

            echo "\nProcessing date: " . $currentDate->toDateString();


            // B. Estraiamo tutte le dirette attive nel giorno (con i loro inserimenti manuali)
            $rawDirette = DB::connection("alternate")->table('dirette AS d')
                ->leftJoin('dirette_inserite_manualmente AS dim', 'd.id', '=', 'dim.idDiretta')
                ->select('d.*', 'dim.id AS Manual_entry_id', 'dim.IdDiretta', 'dim.Dominio', 'dim.ValoreLordo')
                ->where('d.DataInizio', '<=', $currentDate->toDateString())
                ->where('d.DataFine', '>=', $currentDate->toDateString())
                ->get();

            // Raggruppiamo i risultati del JOIN in una struttura simile al Dictionary C#
            $campagnaDictionary = [];
            foreach ($rawDirette as $row) {
                if (!isset($campagnaDictionary[$row->Id])) {
                    // Creiamo l'oggetto Campagna se non esiste
                    $campagnaDictionary[$row->Id] = (object)[
                        'Id' => $row->Id,
                        'IdDirettaDfp' => $row->IdDirettaDfp,
                        'DataInizio' => CarbonImmutable::make($row->DataInizio),
                        'DataFine' => CarbonImmutable::make($row->DataFine),
                        'Dn' => $row->Dn,
                        'Inventory' => $row->Inventory,
                        'InserimentiManuali' => []
                    ];
                }

                // Aggiungiamo l'inserimento manuale se esiste
                if ($row->IdDiretta !== null) {
                    $campagnaDictionary[$row->Id]->InserimentiManuali[] = (object)[
                        'Dominio' => $row->Dominio,
                        'ValoreLordo' => $row->ValoreLordo
                    ];
                }
            }

            // C. Elaboriamo ogni diretta
            $dList = [];
            foreach ($campagnaDictionary as $diretta) {

                // Se IdDirettaDfp è nullo o vuoto, è una diretta manuale
                if (empty(trim($diretta->IdDirettaDfp ?? ''))) {
                    $giorniCampagna = abs($diretta->DataFine->diffInDays($diretta->DataInizio)) + 1;

                    foreach ($diretta->InserimentiManuali as $inserimento) {
                        $entrateLorde = floor(($inserimento->ValoreLordo / $giorniCampagna) * 100) / 100;
                        $valoreDn = floor(($entrateLorde * $diretta->Dn) * 100) / 100;

                        $dList[] = [
                            'date' => $currentDate->toDateString(),
                            'id_ordine' => $diretta->Id,
                            'domain' => $inserimento->Dominio,
                            'ad_unit' => $inserimento->Dominio,
                            'inventory' => $diretta->Inventory,
                            'revenue' => $entrateLorde,
                            'valore_dn' => $valoreDn,
                            'clicks' => 0,
                            'impressions' => 0,
                        ];
                    }
                } // Altrimenti, è una diretta DFP
                else {
                    $diretteDfp = DB::connection("alternate")->table('ssp_dirette_dfp')
                        ->where('date', $currentDate->toDateString())
                        ->where('id_ordine', $diretta->IdDirettaDfp)
                        ->get();

                    foreach ($diretteDfp as $d) {
                        $d->valore_dn = floor(($d->revenue * $diretta->Dn) * 100) / 100;
                        $d->id_ordine = $diretta->Id;
                        $dList[] = (array)$d;
                    }
                }
            }

            // D. Inseriamo i dati nella tabella 'dirette_calcolate'
            echo "===> To insert: " . count($dList) . "...";

            if (!empty($dList)) {
                DB::connection("alternate")->beginTransaction();

                try {
                    // A. Cancelliamo i dati esistenti per il giorno corrente
                    DB::connection("alternate")->table('ssp_dirette_calcolate')
                        ->where('date', $currentDate->toDateString())
                        ->delete();

                    $mapped = collect($dList)->map(function ($item) {
                        return [
                            'date'        => $item['date'] ?? null,
                            'id_ordine'   => $item['id_ordine'] ?? 0,
                            'domain'      => $item['domain'] ?? null,
                            'ad_unit'     => $item['ad_unit'] ?? null,
                            'inventory'   => $item['inventory'] ?? null,
                            'impressions' => $item['impressions'] ?? 0,
                            'clicks'      => $item['clicks'] ?? 0,
                            'revenue'     => $item['revenue'] ?? 0.00,
                            'valore_dn'   => $item['valore_dn'] ?? 0.00,
                        ];
                    })->toArray();
                    DB::connection("alternate")->table('ssp_dirette_calcolate')->insert($mapped);
                } catch (\Exception $e) {
                    DB::connection("alternate")->rollBack();
                    throw $e;
                }
            }

            DB::connection("alternate")->commit();
            echo "Done!";
            $currentDate = $currentDate->addDay();
        }


        echo "Dirette cache created successfully!";
        return 0;
    }
}
