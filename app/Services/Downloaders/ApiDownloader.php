<?php

namespace App\Services\Downloaders;


use Carbon\Carbon;
use Webklex\IMAP\Facades\Client;

class ApiDownloader
{
    public function execute(?array $ssps): array
    {
        $downloadedFiles = [];
        $sources = config('sspscheduler');

        // Filter out only the email sources from the config
        $apiSources = array_filter($sources, function ($source) {
            return $source['type'] === 'api';
        });

        if (empty($apiSources)) {
            return $downloadedFiles;
        }

        try {

            foreach ($apiSources as $sourceName => $config) {
                // The folder name is the same as the source name from the config
                if(count($ssps)!==0 && !in_array(strtolower($sourceName), $ssps, true)) continue;
                $hour=Carbon::now()->hour;
                if (in_array($hour, $config['download_hours'])) {
                    echo "Checking for unseen messages in folder: '{$sourceName}'..." . PHP_EOL;

                    $className= "App\\Services\\Importers\\Ssp\\" . str_replace('_', '', ucfirst(strtolower($sourceName))) . "Manager";

                    $parentClass = '';
                    if (class_exists($className)) {
                        $parentClass = get_parent_class($className);
                    }

                    $extension = ''; // Default extension if no match is found

                    if ($parentClass) {
                        switch ($parentClass) {
                            case 'App\\Services\\Importers\\Parsers\\CsvParser':
                                $extension = 'csv';
                                break;
                            case 'App\\Services\\Importers\\Parsers\\ExcelParser':
                                $extension = 'xlsx'; // or 'xls'
                                break;
                            case 'App\\Services\\Importers\\Parsers\\JsonParser':
                                $extension = 'json';
                                break;
                        }
                    }


                    $date = Carbon::now()->format('Y-m-d_H-i-s');
                    $newFilename = "{$sourceName}_{$date}.{$extension}";
                    $path = storage_path('app/private/ssp/' . $newFilename);

                    $className::downloadFromAPI(null, null, $path);
                    echo "Downloaded file from API as '{$newFilename}' to storage/app/ssp." . PHP_EOL;
logger($path);
                    //ElaboraImportFile::dispatch(($path));



                }



            }

        } catch (\Exception $e) {
            // Handle exceptions, e.g., logging the error
            // Log::error('IMAP connection error: ' . $e->getMessage());
        }


        return $downloadedFiles;
    }
}
