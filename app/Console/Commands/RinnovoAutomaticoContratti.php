<?php
// app/Console/Commands/RinnovoAutomaticoContratti.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Contratto;
use App\Models\Scadenza;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RinnovoAutomaticoContratti extends Command
{
    protected $signature = 'contratti:rinnovo-automatico';
    protected $description = 'Rinnova automaticamente i contratti scaduti';

    public function handle()
    {
        $this->info('Inizio processo di rinnovo automatico contratti...');

        // Trova contratti scaduti e completati che non sono già stati rinnovati
        $contrattiScaduti = Contratto::where('stato', 'completato')
            ->where('data_fine', '<', now())
//            ->whereDoesntHave('scadenzeNonPagate') // Solo se tutte le rate sono pagate
            ->get();
//dd($contrattiScaduti);
        if ($contrattiScaduti->isEmpty()) {
            $this->info('Nessun contratto da rinnovare.');
            return 0;
        }

        $this->info("Trovati {$contrattiScaduti->count()} contratti da rinnovare.");

        $bar = $this->output->createProgressBar($contrattiScaduti->count());
        $bar->start();

        $rinnovati = 0;

        foreach ($contrattiScaduti as $vecchioContratto) {
            try {
                DB::transaction(function () use ($vecchioContratto, &$rinnovati) {
                    // Calcola le nuove date
                    $nuovaDataInizio = $vecchioContratto->data_fine->copy()->addDay();
                    $nuovaDataFine = $nuovaDataInizio->copy()->addYear()->subDay();
                    // Crea nuovo contratto
                    $nuovoContratto = Contratto::create([
                        'piazzola_id' => $vecchioContratto->piazzola_id,
                        'cliente_id' => $vecchioContratto->cliente_id,
                        'data_inizio' => $nuovaDataInizio,
                        'data_fine' => $nuovaDataFine,
                        'valore' => $vecchioContratto->valore,
                        'numero_rate' => $vecchioContratto->numero_rate,
                        'stato' => 'attivo',
                        'rinnovo_automatico' => true,
                        'rinnovo_automatico_at' => now()
                    ]);

                    // Crea le nuove scadenze basate su quelle vecchie
                    $durataMesi = $vecchioContratto->data_inizio->diffInMonths($vecchioContratto->data_fine);
                    $intervalloMesi = $durataMesi / $vecchioContratto->numero_rate;

                    foreach ($vecchioContratto->scadenze as $index => $vecchiaScadenza) {
                        $mesiDaAggiungere = round($intervalloMesi * $index);
                        $nuovaData = $nuovaDataInizio->copy()->addMonths($mesiDaAggiungere);

                        Scadenza::create([
                            'contratto_id' => $nuovoContratto->id,
                            'numero_rata' => $vecchiaScadenza->numero_rata,
                            'data' => $nuovaData,
                            'importo' => $vecchiaScadenza->importo,
                        ]);
                    }

                    $rinnovati++;
                });

            } catch (\Exception $e) {
                $this->error("\nErrore rinnovando contratto {$vecchioContratto->id}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✓ Processo completato! Rinnovati {$rinnovati} contratti.");
        $this->warn("⚠️ I contratti rinnovati necessitano di conferma da parte dell'utente.");

        return 0;
    }
}