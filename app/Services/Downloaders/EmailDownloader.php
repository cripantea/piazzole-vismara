<?php

namespace App\Services\Downloaders;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Webklex\IMAP\Facades\Client;
use GuzzleHttp\Client as HttpClient;
use ZipArchive;

class EmailDownloader
{
    public function execute(?array $ssps): array
    {
        $downloadedFiles = [];
        $sources = config('sspscheduler');

        // Filter out only the email sources from the config
        $emailSources = array_filter($sources, function ($source) {
            return $source['type'] === 'email';
        });

        if (empty($emailSources)) {
            return $downloadedFiles;
        }

        try {
            $client = Client::account('default');
            $client->connect();
            Log::info("Connected to mailbox.");
            $inboxFolder = $client->getFolder('INBOX'); // serve per dopo

            foreach ($emailSources as $sourceName => $config) {
                // The folder name is the same as the source name from the config
                if(count($ssps)!==0 && !in_array(strtolower($sourceName), $ssps, true)) continue;
                Log::info("Checking for unseen messages in folder: '{$sourceName}'...");

                $folder = $client->getFolder($sourceName);

                if (!$folder) {
                    continue; // Skip if the folder doesn't exist
                }
                Log::info("Folder found: '{$sourceName}'.");
                $messages = $folder->messages()->unseen()->get();

                foreach ($messages as $message) {
                    Log::info("Processing message with subject: '{$message->getSubject()}' from folder '{$sourceName}'.");
                    $downloadPath = "email/";
                    $path=null;
                    if ($config['attachment'] === 'file') {
                        Log::info("Configured as attachment...");
                        if($message->hasAttachments()) {
                            Log::info("Go to download attachments.");
                            $path=$this->downloadAttachments($message, str_replace( '_', '',$sourceName));
                        } else {
                            Log::info("But no attachment found...");
                        }
                    } else if ($config['attachment'] === 'link') {
                        Log::info("Configured as link...");
                        $path=$this->downloadFromLink($message, str_replace( '_', '',$sourceName));
                    }

                    // Mark the message as read to avoid re-processing on the next run

                    $message->setFlag('Seen');
                    logger("Message marked as seen.");

                    logger("Archiviando il messaggio: '{$message->getSubject()}'...");

                    //$message->delete();

                    $inboxFolder->messages()->whereMessageId($message->message_id)->get()->first()->delete();


                }
            }

        } catch (\Exception $e) {
            // Handle exceptions, e.g., logging the error
            // Log::error('IMAP connection error: ' . $e->getMessage());
        } catch (\Throwable $e) {
        } finally {
            if (isset($client) && $client->isConnected()) {
                $client->disconnect();
            }
        }

        return $downloadedFiles;
    }
    protected function downloadAttachments($message, $label): ?string
    {

        // Se non ci sono allegati, usciamo subito
        if (!$message->getAttachments()) {
            Log::info("But no attachment found...");
            return null;
        }

        foreach ($message->getAttachments() as $attachment) {
            $filename = $attachment->getFilename();
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $extension = strtolower($extension);

            if($extension==''){
                $filename=$attachment->getAttributes()['name'];
                $extension = pathinfo($filename, PATHINFO_EXTENSION);
                $extension = strtolower($extension);
            }

            Log::info("{$filename} is of type {$extension}. ");

            $allowedExtensions = ['csv', 'json', 'zip', 'xls', 'xlsx'];

            $date = Carbon::parse($message->getDate())->format('Y-m-d_H-i-s');

            if (in_array($extension, $allowedExtensions)) {

                // --- Gestione file ZIP ---
                if ($extension === 'zip') {
                    $zipContent = $attachment->getContent();

                    // Crea un file temporaneo per salvare il contenuto ZIP
                    $tempZipPath = tempnam(sys_get_temp_dir(), 'ssp_zip') . '.zip';
                    file_put_contents($tempZipPath, $zipContent);

                    try {
                        $zip = new ZipArchive();
                        if ($zip->open($tempZipPath) === TRUE) {

                            // Itera su tutti i file all'interno dello ZIP
                            for ($i = 0; $i < $zip->numFiles; $i++) {
                                $entryName = $zip->getNameIndex($i);

                                // Ignora le directory e i file nascosti/di sistema
                                if (substr($entryName, -1) === '/' || str_starts_with(basename($entryName), '__MACOSX')) {
                                    continue;
                                }

                                $content = $zip->getFromIndex($i);
                                $innerExtension = pathinfo($entryName, PATHINFO_EXTENSION);

                                // Costruisce il nome del file interno, mantenendo l'estensione originale
                                $innerNewFilename = "{$label}_{$date}.{$innerExtension}";
                                $innerPath = 'ssp/' . $innerNewFilename;

                                // Salva il contenuto estratto sul disco Laravel 'local'
                                Storage::disk('local')->put($innerPath, $content);

                                Log::info("Extracted and downloaded attachment '{$entryName}' from ZIP as '{$innerNewFilename}' to storage/app/ssp.");

                                // Restituiamo il percorso del primo file valido trovato nello ZIP
                                $zip->close();
                                unlink($tempZipPath); // Pulisce il file temporaneo
                                return $innerPath;
                            }
                            $zip->close();
                        } else {
                            Log::error("Failed to open ZIP archive: " . $filename);
                        }
                    } catch (\Throwable $th) {
                        Log::error("Error processing ZIP file {$filename}: " . $th->getMessage());
                        // Pulizia del file temporaneo in caso di errore
                        if (file_exists($tempZipPath)) {
                            unlink($tempZipPath);
                        }
                        throw $th; // Rilancia l'eccezione per la gestione a livello superiore
                    } finally {
                        if (file_exists($tempZipPath)) {
                            unlink($tempZipPath);
                        }
                    }
                }

                // --- Gestione file CSV/JSON standard (senza ZIP) ---
                else {
                    $newFilename = "{$label}_{$date}.{$extension}";
                    $path = 'ssp/' . $newFilename;

                    try {
                        Storage::disk('local')->put($path, $attachment->getContent());
                        Log::info("Saved attachement {$path}. ");
                    } catch (\Throwable $th) {
                        throw $th;
                    }
                    return $path;
                }
            } else {
                Log::warning("Skipping attachment '{$filename}' as it is not a supported file type ({$extension}).");
            }
        }

        return null;
    }
    protected function downloadFromLink($message, $label): ?string
    {
        $body = $message->getHTMLBody();
        if (empty($body)) {
            logger("Message has no HTML body to scan for links.");
            return null;
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($body);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);
        $links = $xpath->query("//a");

        foreach ($links as $link) {
            $text = trim($link->textContent);
            if (stripos($text, 'download') !== false) {
                $href = $link->getAttribute("href");
                logger("Found a 'download' link with URL: '{$href}'");

                try {
                    $http = new HttpClient();
                    $response = $http->get($href);
                    $content = $response->getBody()->getContents();

                    $contentType = $response->getHeader('Content-Type')[0] ?? null;
                    $extension = $this->determineFileType($content);

                    if ($extension === 'csv' || $extension === 'json' || $extension === 'zip') {
                        $date = Carbon::parse($message->getDate())->format('Y-m-d_H-i-s');
                        $newFilename = "{$label}_{$date}.{$extension}";
                        $path = 'ssp/' . $newFilename;


                        Storage::disk('local')->put($path, $content);
                        logger("Downloaded file from link as '{$newFilename}' to storage/app/ssp.");
                        return storage_path('app/' . $path);
                    } else {
                        logger("Skipping download from link. The file type is not CSV or JSON.");
                    }

                } catch (\Exception $e) {
                    logger("Failed to download from link: " . $e->getMessage());
                }
            }
        }
        logger("No 'download' link found in the message body.");
        return null;
    }
    protected function determineFileType(string $content): ?string
    {
        $content = trim($content);

        // 1. Check for JSON (Standard/Full Body JSON)
        // Checks if the entire content is a valid JSON object or array.
        if (json_decode($content) !== null && json_last_error() === JSON_ERROR_NONE) {
            return 'json';
        }

        // Prepare content for line-based checks (for JSONL and CSV)
        // Split content into lines and filter out any empty lines.
        $lines = array_filter(explode("\n", $content), 'trim');
        $linesToCheck = array_slice($lines, 0, 10); // Only check up to the first 10 non-empty lines

        // 2. Check for Line-delimited JSON (JSONL)
        // Assumes each line is a valid JSON object.
        if (count($linesToCheck) > 1) {
            $isJsonLines = true;
            foreach ($linesToCheck as $line) {
                // If we find any non-empty line that isn't valid JSON, it's not JSONL
                if (!empty($line) && json_decode($line) === null && json_last_error() !== JSON_ERROR_NONE) {
                    $isJsonLines = false;
                    break;
                }
            }
            if ($isJsonLines) {
                return 'json';
            }
        }

        // 3. Check for CSV (including tab-delimited)
        // A robust heuristic: Check if *any* of the first few non-empty lines contains common delimiters.
        foreach ($linesToCheck as $line) {
            // If the line contains a comma, semicolon, or tab, it is highly likely to be the CSV header or data row.
            if (strpos($line, ',') !== false || strpos($line, ';') !== false || strpos($line, "\t") !== false) {
                // To prevent false positives from commas in plain text, we can require more than one delimiter,
                // but for simple file type detection after JSON checks, the presence is often enough.
                // Let's stick with presence check for flexibility with single-column CSVs.
                return 'csv';
            }
        }

        // 4. Check for ZIP files (simple magic number check on the first few bytes)
        // The local file signature for a ZIP archive is PK\x03\x04
        if (str_starts_with($content, "PK\x03\x04")) {
            return 'zip';
        }

        return null;
    }
}
