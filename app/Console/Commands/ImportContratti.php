<?php
// app/Console/Commands/ImportContratti.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Piazzola;
use App\Models\Cliente;
use App\Models\Contratto;
use App\Models\Scadenza;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ImportContratti extends Command
{
    protected $signature = 'contratti:import {file}';
    protected $description = 'Importa contratti e scadenze da file di testo';

    public function handle()
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("File non trovato: {$filePath}");
            return 1;
        }

        $this->info("Inizio importazione da: {$filePath}");

        // Leggi il file
        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);

        // Rimuovi la prima riga (intestazione) e la seconda (vuota)
        array_shift($lines);
        array_shift($lines);

        $contratti = [];
        $errori = [];

        foreach ($lines as $index => $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Dividi per TAB
            $columns = explode("\t", $line);

            if (count($columns) < 4) {
                $errori[] = "Riga " . ($index + 1) . ": numero colonne insufficiente";
                continue;
            }

            $piazzolaIdentificativo = trim($columns[0]);
            $clienteNome = trim($columns[1]);
            $dataContrattoStr = trim($columns[2]);
            $valoreStr = trim($columns[3]);

            // Salta se mancano dati essenziali
            if (empty($piazzolaIdentificativo) || empty($clienteNome) || empty($dataContrattoStr) || empty($valoreStr)) {
                continue;
            }

            try {
                // Parse data contratto
                $annoCorrenteDueCifre = substr($dataContrattoStr, -2);
echo $dataContrattoStr . "\n";
                if ($annoCorrenteDueCifre !== '25' && $annoCorrenteDueCifre !== '26') {
echo $annoCorrenteDueCifre . "\n";
                    $dataContrattoStr = substr_replace($dataContrattoStr, '25', -2);
echo $dataContrattoStr . "\n";
                }
                echo $dataContrattoStr . "\n";
                $dataContratto = $this->parseData($dataContrattoStr);
echo $dataContratto . "\n";
                // Parse valore (rimuovi simbolo euro, spazi, punti separatori migliaia)
                $valore = $this->parseValore($valoreStr);

                // Raccogli le date delle scadenze
                $dateScadenze = [];
                for ($i = 4; $i < count($columns); $i++) {
                    $dataScadenzaStr = trim($columns[$i]);
                    if (!empty($dataScadenzaStr)) {
                        $dateScadenze[] = $dataScadenzaStr;
                    }
                }

                $contratti[] = [
                    'piazzola_identificativo' => $piazzolaIdentificativo,
                    'cliente_nome' => $clienteNome,
                    'data_inizio' => $dataContratto,
                    'valore' => $valore,
                    'date_scadenze' => $dateScadenze
                ];

            } catch (\Exception $e) {
                $errori[] = "Riga " . ($index + 1) . ": " . $e->getMessage();
            }
        }

        $this->info("Contratti da importare: " . count($contratti));

        if (!empty($errori)) {
            $this->warn("\nErrori riscontrati:");
            foreach ($errori as $errore) {
                $this->warn("  - " . $errore);
            }
        }

        if (!$this->confirm('Procedere con l\'importazione?')) {
            $this->info('Importazione annullata.');
            return 0;
        }

        // Importa i contratti
        $bar = $this->output->createProgressBar(count($contratti));
        $bar->start();

        $importati = 0;
        $erroriImport = [];

        foreach ($contratti as $contrattoData) {
            try {
                DB::transaction(function () use ($contrattoData, &$importati) {
                    // Trova o crea piazzola
                    $piazzola = Piazzola::firstOrCreate(
                        ['identificativo' => $contrattoData['piazzola_identificativo']],
                        ['nome' => $contrattoData['piazzola_identificativo']]
                    );

                    // Trova o crea cliente
                    $cliente = Cliente::firstOrCreate(
                        ['nome' => $contrattoData['cliente_nome']]
                    );

                    // Calcola data fine (1 anno dopo data inizio)
                    $dataInizio = Carbon::parse($contrattoData['data_inizio']);
                    $dataFine = $dataInizio->copy()->addYear()->subDay();

                    // Determina numero rate
                    $dateScadenze = $contrattoData['date_scadenze'];
                    $numeroRate = count($dateScadenze);

                    // Se prima scadenza dice "tutti i mesi", crea 12 rate
                    if ($numeroRate > 0 && stripos($dateScadenze[0], 'tutti i mesi') !== false) {
                        $numeroRate = 12;
                        $dateScadenze = $this->generaDateMensili($dataInizio);
                    }

                    // Crea contratto
                    $contratto = Contratto::create([
                        'piazzola_id' => $piazzola->id,
                        'cliente_id' => $cliente->id,
                        'data_inizio' => $dataInizio,
                        'data_fine' => $dataFine,
                        'valore' => $contrattoData['valore'],
                        'numero_rate' => $numeroRate,
                        'stato' => 'attivo'
                    ]);

                    // Calcola importo per rata
                    $importoRata = round($contrattoData['valore'] / $numeroRate, 2);

                    // Aggiusta l'ultima rata per eventuali differenze di arrotondamento
                    $sommaRate = $importoRata * ($numeroRate - 1);
                    $ultimaRata = $contrattoData['valore'] - $sommaRate;

                    // Crea scadenze
                    for ($i = 0; $i < $numeroRate; $i++) {
                        $dataScadenza = $this->parseData($dateScadenze[$i]);
                        $importo = ($i == $numeroRate - 1) ? $ultimaRata : $importoRata;

                        Scadenza::create([
                            'contratto_id' => $contratto->id,
                            'numero_rata' => $i + 1,
                            'data' => $dataScadenza,
                            'importo' => $importo,
                        ]);
                    }

                    $importati++;
                });

            } catch (\Exception $e) {
                $erroriImport[] = "Contratto {$contrattoData['piazzola_identificativo']} - {$contrattoData['cliente_nome']}: " . $e->getMessage();
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✓ Importazione completata!");
        $this->info("  Contratti importati: {$importati}");

        if (!empty($erroriImport)) {
            $this->error("\nErrori durante l'importazione:");
            foreach ($erroriImport as $errore) {
                $this->error("  - " . $errore);
            }
        }

        return 0;
    }

    /**
     * Parse data italiana (formato: 1-gen-25, 10-mar-16, ecc.)
     */
    private function parseData($dataStr)
    {
        // Rimuovi spazi
        $dataStr = trim($dataStr);

        // Mappa mesi italiani
        $mesi = [
            'gen' => '01', 'feb' => '02', 'mar' => '03', 'apr' => '04',
            'mag' => '05', 'giu' => '06', 'lug' => '07', 'ago' => '08',
            'set' => '09', 'ott' => '10', 'nov' => '11', 'dic' => '12'
        ];

        // Pattern: 1-gen-25
        if (preg_match('/(\d+)-(\w+)-(\d+)/', $dataStr, $matches)) {
            $giorno = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $meseStr = strtolower($matches[2]);
            $anno = $matches[3];

            // Converti mese
            $mese = $mesi[$meseStr] ?? '01';

            // Converti anno (se < 25, considera 2025+, altrimenti 1900+)
            if (strlen($anno) == 2) {
                $annoInt = intval($anno);
                // Se l'anno è precedente al 2025, usa 2025
                if ($annoInt < 25 || $annoInt >= 26) {
                    $anno = '20' . $anno;
                } else {
                    $anno = '2025'; // Forza 2025 per contratti vecchi
                }
            }

            return Carbon::createFromFormat('Y-m-d', "{$anno}-{$mese}-{$giorno}");
        }

        throw new \Exception("Formato data non riconosciuto: {$dataStr}");
    }

    /**
     * Parse valore (€732,00 o 793,00 € o €1.708,00)
     */
    private function parseValore($valoreStr)
    {
        // Rimuovi simbolo euro, spazi, e altri caratteri non numerici tranne virgola e punto
        $valoreStr = preg_replace('/[^\d,\.]/', '', $valoreStr);

        // Sostituisci virgola con punto
        $valoreStr = str_replace(',', '.', $valoreStr);

        // Se ci sono più punti, rimuovi quelli che sono separatori di migliaia
        // (es: 1.708.00 -> 1708.00)
        $parts = explode('.', $valoreStr);
        if (count($parts) > 2) {
            // L'ultimo è il decimale, gli altri sono separatori di migliaia
            $decimali = array_pop($parts);
            $valoreStr = implode('', $parts) . '.' . $decimali;
        }

        return (float) $valoreStr;
    }

    /**
     * Genera 12 date mensili a partire dalla data di inizio
     */
    private function generaDateMensili($dataInizio)
    {
        $date = [];
        for ($i = 0; $i < 12; $i++) {
            $data = $dataInizio->copy()->addMonths($i);
            $date[] = $data->format('j-M-y');
        }
        return $date;
    }
}