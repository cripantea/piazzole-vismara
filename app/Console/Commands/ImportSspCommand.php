<?php

namespace App\Console\Commands;

use App\Services\Downloaders\ApiDownloader;
use App\Services\Downloaders\EmailDownloader;
use App\Services\Importers\Jobs\ElaboraImportFiles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use GuzzleHttp\Client as HttpClient;
use Webklex\IMAP\Facades\Client;

class ImportSspCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dash:import-ssp {--run=all : Specifica quale procedura eseguire (email, api, o all)} {--onlyImport} {--ssp=*} {--file=}';
/**/
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        $onlyImport = $this->option('onlyImport');

        $ssps = $this->option('ssp');


        $option = strtolower($this->option('run'));

        $fileName=$this->option('file');

Log::info("Start loading files");
Log::info("Parameters: \nrun: {$option} \nonlyImport: {$onlyImport} \nssps: ".implode(',',$ssps)." \nfile: {$fileName}");


        if($fileName){
            if(File::exists($fileName)){
                ElaboraImportFiles::processFileImport($fileName);
                return;
            } else {
                Log::info("File {$fileName} non trovato");
                return;
            }
        }


        $runEmail = in_array($option, ['email', 'all']);
        $runApi = in_array($option, ['api', 'all']);

        if(!$onlyImport) {
            if ($runEmail) {
                Log::info( "Start run email download");
                (new EmailDownloader())->execute($ssps);
            }

            logger("Fine Email Start loading files");

            if ($runApi) {
                Log::info("Start run api download");
                (new ApiDownloader())->execute($ssps);
            }
        }

        Log::info( "Start run elabora files");
         (new ElaboraImportFiles())->execute($ssps);
    }

    private function processEmailSources(): int
    {
        $this->info("Connecting to mailbox...");

        try {
            // Get the IMAP client instance
            $client = Client::account('default');
            $client->connect();
            $this->info("Successfully connected to mailbox.");

            $totalProcessed = 0;

            $folderInbox=$client->getFolder('INBOX');

            foreach ($this->gmailLabels as $label) {
                try {

                    $class = "Modules\\Importer\\Ssp\\" . str_replace('_', '', ucfirst($label)) . "Manager";

                    $this->info("Checking for unseen messages in folder: '{$label}'...");

                    $folder = $client->getFolder($label);

                    if(!$folder){
                        $this->info("'{$label}' folder does not exists.");
                        continue;
                    }
                    // Fetch unseen messages from this specific folder
                    $messages = $folder->messages()->unseen()->get();

                    if ($messages->count() === 0) {
                        $this->info("No new unseen messages in '{$label}'.");
                        continue;
                    }

                    $this->info("Found {$messages->count()} new unseen messages in '{$label}'.");

                    foreach ($messages as $message) {
                        $subject = mb_decode_mimeheader($message->getSubject());
                        $this->info("Processing message with subject: '{$subject}' from label '{$label}'.");
                        $fullFileName='';
                        // Try to download an attachment first

// nel nome del file data del messaggio.
// questa if con delle regole in base al SSP da prendere da c#

                        if ($message->hasAttachments()) {
                            $fullFileName=$this->downloadAttachments($message, $label);
                        } else {
                            // If no attachments, try to download from a link in the body
                            $fullFileName=$this->downloadFromLink($message, $label);
                        }


                        //$message->setFlag('Seen');
                        $this->info("Message marked as seen.");

                        $this->info("Archiviando il messaggio: '{$message->getSubject()}'...");

                        //$message->delete();

                        //$folderInbox->messages()->whereMessageId($message->message_id)->get()->first()->delete();

                        ElaboraImportFile::dispatch(($fullFileName));

                        $this->line(''); // Add a blank line for readability
                        $totalProcessed++;
                    }
                } catch (\Exception $e) {
                    $this->warn("La cartella '{$label}' non esiste o c'è un errore: " . $e->getMessage() . ". Saltando...");
                    continue;
                }
            }

            if ($totalProcessed > 0) {
                $this->info("Email processing complete. Total messages processed: {$totalProcessed}.");
            } else {
                $this->info("No messages were processed.");
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("An error occurred: " . $e->getMessage());
            return 1;
        }
    }
    protected function downloadAttachments($message, $label): ?string
    {
        foreach ($message->getAttachments() as $attachment) {
            $filename = $attachment->getFilename();
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $extension = strtolower($extension);

            if ($extension === 'csv' || $extension === 'json') {
                $newFilename = "{$label}.{$extension}";
                $path = storage_path('ssp/') . $newFilename;
                try {
                    Storage::disk('local')->put($path, $attachment->getContent());
                } catch (\Throwable $th) {
                    throw $th;
                }

                $this->info("Downloaded attachment '{$filename}' as '{$newFilename}' to storage/app/ssp.");
                return $path;
            } else {
                $this->warn("Skipping attachment '{$filename}' as it is not a CSV or JSON file.");

            }

        }
        return null;
    }

    /**
     * Scans the message body for a download link and downloads the file.
     *
     * @param object $message
     * @param string $label
     */
    protected function downloadFromLink($message, $label): ?string
    {
        $body = $message->getHTMLBody();
        if (empty($body)) {
            $this->warn("Message has no HTML body to scan for links.");
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
                $this->info("Found a 'download' link with URL: '{$href}'");

                try {
                    $http = new HttpClient();
                    $response = $http->get($href);
                    $content = $response->getBody()->getContents();

                    $contentType = $response->getHeader('Content-Type')[0] ?? null;
                    $extension = $this->determineExtension($contentType, $href);

                    if ($extension === 'csv' || $extension === 'json') {
                        $newFilename = "{$label}.{$extension}";
                        $path = 'ssp/' . $newFilename;
                        Storage::disk('local')->put($path, $content);
                        $this->info("Downloaded file from link as '{$newFilename}' to storage/app/ssp.");
                        return storage_path('app/' . $path);
                    } else {
                        $this->warn("Skipping download from link. The file type is not CSV or JSON.");
                    }

                } catch (\Exception $e) {
                    $this->error("Failed to download from link: " . $e->getMessage());
                }
            }
        }
        $this->info("No 'download' link found in the message body.");
        return null;
    }

    /**
     * Determines the file extension from Content-Type header or URL.
     *
     * @param string|null $contentType
     * @param string $href
     * @return string|null
     */
    protected function determineExtension($contentType, $href): ?string
    {
        if (stripos($contentType, 'csv') !== false) {
            return 'csv';
        }
        if (stripos($contentType, 'json') !== false) {
            return 'json';
        }
        $pathInfo = pathinfo(parse_url($href, PHP_URL_PATH), PATHINFO_EXTENSION);
        if ($pathInfo) {
            return strtolower($pathInfo);
        }
        return null;
    }
    private function processApiSources()
    {

    }



}
