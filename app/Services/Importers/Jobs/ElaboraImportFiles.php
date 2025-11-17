<?php

namespace App\Services\Importers\Jobs;

use App\Services\SspReader;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Event\Runtime\PHP;

class ElaboraImportFiles
{


    /**
     * Create a new job instance.
     *
     * @param string $filename
     * @return void
     */
    public function __construct()
    {

    }

    /**
     * Execute the job.
     */
    public function execute(array|null $ssps): void {

        $basePath = 'ssp/';
        $fullBasePath = Storage::disk('local')->path('ssp');

        Log::info("Avvio dell'elaborazione dei file dalla directory: {$fullBasePath}");
        echo "Avvio dell'elaborazione dei file dalla directory: {$fullBasePath}\n";
        echo "1- ssps: ".implode(',',$ssps)."\n";

        $allFiles = array_filter(
            Storage::disk('local')->files($basePath),
            fn ($file) => !str_starts_with(basename($file), '.')
        );

        if (empty($allFiles)) {
            Log::info('Nessun file trovato in storage/app/ssp. Comando completato.');
            return;
        }

        $sortedFiles = $this->groupAndSortFiles($allFiles);

        echo "2- sortedFiles: ".implode(PHP_EOL,$sortedFiles->pluck('path')->toArray())."\n";

        $this->processImports($sortedFiles, $ssps);

        Log::info('Processo di importazione completato per tutti i file.');
    }

    protected function processImports(Collection $sortedFiles, array $ssps)
    {
        echo "4-ssps: ".implode(',',$ssps)."\n";

        foreach ($sortedFiles as $fileData) {
            $fullPathFileName = Storage::disk('local')->path($fileData['path']);
            $ssp = SspReader::extractSsp(basename($fullPathFileName));

            if(count($ssps)!==0 && !in_array($ssp, $ssps, true)) {
                continue;
            }

            echo "5-ssp inizio elaborazione: {$ssp}\n";

            Log::info("\n--- Elaborazione: {$ssp}/{$fullPathFileName}");

            try {
                // Esempio: Chiama il metodo di importazione sul manager
                // Assumi che $manager::getImporter($sspClassPrefix) restituisca l'istanza corretta
                $class = "App\\Services\\Importers\\Ssp\\" . ucfirst($ssp) . "Manager";
                $parser = app($class);
                $parser->import($fullPathFileName);

                Log::info (" [OK] Importazione completata per: " . basename($fullPathFileName));

                // ARCHIVIAZIONE: Muove il file processato nella cartella 'processed'
                $processedPath = 'ssp/processed/' . basename($fileData['path']);
                Storage::disk('local')->move($fileData['path'], $processedPath);
                Log::info(" [Archiviato] File spostato in: storage/app/{$processedPath}");

            } catch (\Throwable $e) {
                Log::error(" [ERRORE] Fallita l'importazione per " . basename($fullPathFileName));
                Log::error(" Messaggio: " . $e->getMessage());
                // Non interrompere l'intero processo per un singolo errore. Il file fallito rimane in /ssp.
            }

        }
        echo "\n";
    }

    public static function processFileImport(string $fileName)
    {
        echo "4.1-file: ".$fileName."\n";

        $ssp = SspReader::extractSsp(basename($fileName));


        Log::info("\n--- Elaborazione: {$ssp}/{$fileName}");

        try {
            $class = "App\\Services\\Importers\\Ssp\\" . ucfirst($ssp) . "Manager";
            $parser = app($class);
            $parser->import($fileName);

            echo (" [OK] Importazione completata per: " . basename($fileName));

            $processedPath = storage_path('app/private/ssp/processed/') . basename($fileName);
            echo $fileName.PHP_EOL;
            echo $processedPath . PHP_EOL;
            File::move($fileName, $processedPath);
            Log::info(" [Archiviato] File spostato in: storage/app/{$processedPath}");

        } catch (\Throwable $e) {
            Log::info(" [ERRORE] Fallita l'importazione per " . basename($fileName));
            Log::info(" Messaggio: " . $e->getMessage());
        }
    }


    protected function groupAndSortFiles(array $files): Collection
    {
        // Raggruppa i file per prefisso (es. Amazon, Criteo)
        $grouped = collect($files)->map(function ($path) {
            $filename = basename($path);

            // Estrazione del prefisso (es. 'Amazon' da 'Amazon_2025-09-22_16-52-58.json')
            if (preg_match('/^([a-zA-Z]+)_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\./', $filename, $matches)) {
                $prefix = $matches[1];
                $dateTimeString = str_replace('_', ' ', $matches[2]); // Formato Carbon: YYYY-MM-DD HH:mm:ss

                return [
                    'path' => $path,
                    'prefix' => $prefix,
                    'datetime' => Carbon::createFromFormat('Y-m-d H-i-s', $dateTimeString),
                ];
            }

            // Se il file non segue il pattern, lo mettiamo in un gruppo 'Other'
            return [
                'path' => $path,
                'prefix' => 'Other',
                'datetime' => Carbon::parse('2000-01-01 00:00:00') // Ordina i file sconosciuti per primi
            ];
        })
            ->groupBy('prefix') // Raggruppa in base al prefisso (Amazon => [...], Criteo => [...])
            ->map(function (Collection $filesGroup) {
                // Ordina i file all'interno di ogni gruppo per data (dal più vecchio al più recente)
                return $filesGroup->sortBy('datetime');
            });

        // Appiattisce la Collection in una singola lista ordinata per prefisso e poi per data
        return $grouped->flatten(1);
    }

}
