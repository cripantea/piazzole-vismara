<?php

namespace App\Services\Importers\Ssp;

use App\Services\Importers\Helpers\ImportersHelper;
use App\Services\SspReader;
use Carbon\CarbonImmutable;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Services\Importers\Interfaces\SspDownloadInterface;
use App\Services\Importers\Parsers\CsvParser;
use App\Services\Importers\Parsers\JsonParser;
use App\Services\Importers\Interfaces\CacheBuilderInterface;

class BridManager extends JsonParser implements SspDownloadInterface, CacheBuilderInterface
{
    public function __construct(
        public ?CarbonImmutable $date = null,
        public ?string $domain = null,
        public ?int $impressions = null,
        public ?BigDecimal $ecpm = null,
    ) {}

    public static $mapping = [
        'date' => 'Data',
        'domain' => 'Dominio',
        'impressions' => 'Impressioni',
        'ecpm' => 'eCPM',
    ];

public static function downloadFromAPI(string|null $fromDate, string|null $toDate, ?string $fileName)
{
$ab=1;



        $getBridDataByProduct = function($product) {


            $makePostRequest = function(string $product, string $key, Client $client) {
                $retries = 0;
                $data = "";
                $token = "e6bc0cedb8885a9017c5d68b7b1b15800a614016";
                while ($retries < 2) {
                    try {
                        $response = $client->post(
                            "https://api.brid.tv/apiv3/stat/partner/ads/{$key}.json",
                            [
                                'form_params' => [
                                    'data[date_from]' => date('Y-m-d', strtotime('-15 days')),
                                    'data[date_to]' => date('Y-m-d'),
                                    'data[metric]' => 'Overview',
                                    'data[filter]' => 'all',
                                    'data[results]' => 'by day',
                                    'data[product]' => $product
                                ],
                                'headers' => [
                                    'User-Agent' => 'Api | BridVideo',
                                    'Authorization' => 'Bearer '.$token
                                ]
                            ]
                        );

                        $data = (string) $response->getBody();
                        break;

                    } catch (\Exception $e) {
                        $retries++;
                        if ($retries >= 2) {
                            throw new \Exception("Max retries exceeded for POST request.");
                        }
                        sleep(3);
                    }
                }
                return $data;
            };

            $bridData = [];
            $token = "e6bc0cedb8885a9017c5d68b7b1b15800a614016";
            $url = "https://api.brid.tv/apiv3/partner/list.json";

            // Create a Guzzle client instance for making API calls.
            $client = new Client();

            try {
                // Make the GET request to the partners list API
                $response = $client->get($url, [
                    'headers' => [
                        'Authorization' => "Bearer " . $token,
                        'User-Agent' => "Api | BridVideo"
                    ]
                ]);

                $result = (string) $response->getBody();
                $res = json_decode($result, false); // Use false for object, true for array

                foreach ($res as $key => $domain) {
                    echo $product.'  '.$domain."\n";
                    try {
                        // Call the Guzzle-based POST request method
                        $data = $makePostRequest($product, $key, $client);
                        $d = json_decode($data);

                        if (isset($d->Stats)) {
                            foreach ($d->Stats as $date_str => $stat) {
                                $impressions = $stat->Impression ?? null;
                                if ($impressions === null) {
                                    continue;
                                }

                                $date = CarbonImmutable::createFromFormat('Y-m-d', $date_str);
                                if ($date === false) {
                                    continue;
                                }

                                $isDuplicate = false;
                                foreach ($bridData as $existingItem) {
                                    if ($existingItem['Data']->format('Y-m-d') == $date->format('Y-m-d') && $existingItem['Dominio'] == $domain) {
                                        $isDuplicate = true;
                                        break;
                                    }
                                }

                                if ($isDuplicate) {
                                    throw new \Exception("Brid duplicate");
                                }

                                $newItem = [
                                    'Data'=> $date,
                                    'Dominio'=> $domain,
                                    'Impressioni'=> (int) $impressions,
                                    'Ecpm' => null

                                ];
                                $bridData[]=$newItem;
                            }
                        }
                    } catch (\Exception $ex) {
                        echo $ex->getMessage(). "\n";
                        return null;
                    }
                }
                return $bridData;

            } catch (\Exception $e) {
                echo "Error fetching partners list: " . $e->getMessage() . "\n";
                return [];
            }
        };

    //    public function internalRead() {
        $player = $getBridDataByProduct("player");
//print_r($player);
        $outstream = $getBridDataByProduct("outstream");
//dd($outstream);
    //     // Combine and group data
        $bridData = [];
        $combinedData = array_merge($player, $outstream);
        try {
            foreach ($combinedData as $item) {
                $key = $item['Data']->format('Ymd') . '|' . $item['Dominio'];
                if (!isset($bridData[$key])) {
                    $newItem =[
                        'Data'=> $item['Data'],
                        'Dominio'=> $item['Dominio'],
                        'Impressioni'=> 0,
                        'Ecpm' => null
                    ];
                    $bridData[$key] = $newItem;
                }
                if(!isset($bridData[$key]['Impressioni'])) {
                    $bridData[$key]['Impressioni']=0;
                }
                $bridData[$key]['Impressioni'] += $item['Impressioni'];
            }

        } catch (\Exception $e) {
            echo $e->getMessage();
        }
        // Group by Data and Dominio

    //     // Convert the associative array back to a simple list
         $bridData = json_encode(array_values($bridData));

    //     // Get current directory
    //     $currentDirectory = getcwd();

        $filePath = $fileName ?? SspReader::generateOutFileName("brid", "json", $fromDate, $toDate);
        file_put_contents($filePath, $bridData);
    //     // Write data to JSON files
    //     file_put_contents($currentDirectory . "/data_brid.json", json_encode($bridData, JSON_PRETTY_PRINT));
    //     file_put_contents($currentDirectory . "/data_brid_player.json", json_encode($player, JSON_PRETTY_PRINT));
    //     file_put_contents($currentDirectory . "/data_brid_outstream.json", json_encode($outstream, JSON_PRETTY_PRINT));

    //     // Create and return JobWorkItem
    //     $workItem = new JobWorkItem();
    //     $workItem->DataLettura = date('Y-m-d H:i:s');
    //     $workItem->EmailUniqueId = 0;
    //     $workItem->SSP = EnumSSP::BRID;
    //     $workItem->Dati = json_encode($bridData);
    //     $workItem->FormatoDati = EnumTipoFormatoDati::JSON;

    //     return $workItem;
    // }


}

    public function convert(array $parsed): Collection
    {
        $rows = $parsed;
        $bridEcpms= DB::connection('alternate')->table('storico_ecpm_brid');
        $result = collect();
        foreach ($rows as $riga) {

            try {
                $dateEcpm=CarbonImmutable::parse($riga['Data'])->firstOfMonth()->toDateString();
                $ecpm=DB::connection('alternate')->table('storico_ecpm_brid')->where('data', $dateEcpm)->first();//->pluck('valore');
                $result->push(new self(
                    date:CarbonImmutable::parse($riga['Data']),
                    domain: $riga['Dominio'],
                    impressions: $riga['Impressioni'],
                    ecpm: BigDecimal::of($ecpm->valore),
                ));

            }catch (\Exception $e) {
                echo $e->getTraceAsString();
            }
        }
        $grouped = $result->groupBy(fn($item) => $item->date->format('Y-m-d') . '|' . $item->domain)
                ->map(function ($items) {
                    $first = $items->first();

                    return new self(
                        date: $first->date,
                        domain: $first->domain,
                        impressions: $items->sum(fn($i) => $i->impressions),
                        ecpm: $first->ecpm,
                    );
                })
                ->values();
logger(print_r($grouped, true));
        return $grouped;


    }

    public function getDelimiter(): string
    {
        // --- MARKER: CUSTOM DELIMITER LOGIC REQUIRED HERE ---
        // This method should return the delimiter specific to your CSV file.
        // Example: return "AdUnit";
        // Example: return "another_csv_specific_delimiter";
        // --- END MARKER ---
        return ""; // Placeholder
    }

    public function getTableName(): string
    {
        return "ssp_brid";
    }

    public function getCsvDelimiter(): string
    {
        // --- MARKER: CUSTOM CSV DELIMITER LOGIC REQUIRED HERE ---
        // This method should return the CSV field delimiter (e.g., ",", "\t", ";").
        // Example: return "\t"; // For tab-separated values
        // Example: return ","; // For comma-separated values
        // --- END MARKER ---
        return ""; // Placeholder
    }

    // Dummy implementation con errore intenzionale




    /**
     * @param DateTime $currentDate
     * @param string $tableName
     * @param string $inventory
     * @param float|null $ecpm
     * @return array<object>
     * * @throws Exception
     */


    public function getDomainCache(?CarbonImmutable $current_date): array
    {

        // dal primo agosto 2025 portiamo la fee di BRID in euro
        $comparisonDate = CarbonImmutable::parse('2025-08-01');

        if ($current_date >= $comparisonDate) {
            // default 0,14€ (assumendo che la fee sia 0.14)
            $domains=ImportersHelper::getEurEcpmCostList($current_date, $this->getTableName(), "VIDEO", BigDecimal::of('0.14'));
        } else {
            // prima del primo agosto 2025 rimane in dollari
            $domains= ImportersHelper::getUsdEcpmCostList($current_date, $this->getTableName(), "VIDEO");
        }


        return [
            'domains' => $domains,
            'deals' => [],
        ];
    }
}
