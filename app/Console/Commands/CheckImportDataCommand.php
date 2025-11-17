<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PHPUnit\Event\Runtime\PHP;

class CheckImportDataCommand extends Command
{
    protected $signature = 'dash:check-import-data';

    protected $description = 'Command description';

    private $ssps=[
        'ebda' => [
            'entrate' => 'Entrate',
            'impressioni' => 'Impressioni',
            'richieste' => 'Richieste',
        ],
        'amazon' => [
            'entrate' => 'Entrate',
            'impressioni' => 'Impressioni',
            'richieste' => 'Richieste',
        ],
        'dirette_dfp'=>[
            'entrate' => 'Entrate',
            'impressioni' => 'Impressioni',
            'richieste' => 'Richieste',
        ],
        'data_google'=>[
            'entrate' => 'EntrateStimate',
            'impressioni' => 'Impressioni',
            'richieste' => 'Richieste',
        ],
        'connectad'=>[
            'entrate' => 'Entrate',
            'impressioni' => 'Impressioni',
            'richieste' => 'Richieste',
        ],
        'google_deals'=>[
            'entrate' => 'Entrate',
            'impressioni' => 'Impressioni',
            'richieste' => 'Richieste',
        ],
        'criteo'=>[
            'entrate' => 'Entrate',
            'impressioni' => 'Impressioni',
            'richieste' => 'Richieste',
        ],
        'ogury'=>[
            'entrate' => 'Entrate',
            'impressioni' => 'Impressioni',
            'richieste' => 'Richieste',
        ],
        'brid'=>[
            'entrate' => 'Entrate',
            'impressioni' => 'Impressioni',
            'richieste' => 'Richieste',
        ],
        'google_impressions_details'=>[
            'entrate' => 'Entrate',
            'impressioni' => 'Impressioni',
            'richieste' => 'Richieste',
        ],
        'outbrain'=>[
            'entrate' => 'Entrate',
            'impressioni' => 'Impressioni',
            'richieste' => 'Richieste',
        ],
        'richaudience'=>[
            'entrate' => 'Entrate',
            'impressioni' => 'Impressioni',
            'richieste' => 'Richieste',
        ],
        'seedtag'=>[
            'entrate' => 'Entrate',
            'impressioni' => 'Impressioni',
            'richieste' => 'Richieste',
        ]
    ];


    public function handle(): void
    {
        $last_date=CarbonImmutable::yesterday()->subDay();
        $first_date=$last_date->subDays(7);


        $last_date=$last_date->format('Y-m-d');
        $first_date=$first_date->format('Y-m-d');

        $exception=[];
        foreach ($this->ssps as $ssp=>$ssp_data){

            try {
                $a = (DB::connection('alternate')->select("select sum(revenue) as newRevenue from ssp_{$ssp} where date between '$first_date' AND '$last_date'")[0])->newRevenue;
                $b = DB::connection('maindash')->select("select sum(" . $ssp_data['entrate'] . ") as oldRevenue from {$ssp} where Data between '$first_date' AND '$last_date'")[0]->oldRevenue;
                if ($a == $b) {
                    echo "$ssp revenues for $first_date => $last_date are OK" . PHP_EOL;
                    echo "New -" . $a . PHP_EOL;
                    echo "Old -" . $b . PHP_EOL;
                } else {
                    $exception[]=[
                        "$ssp revenues for $first_date => $last_date are NOT OK" . PHP_EOL,
                        "New -" . $a . PHP_EOL,
                        "Old -" . $b . PHP_EOL,
                    ];
                    echo "$ssp revenues for $first_date => $last_date are NOT OK" . PHP_EOL;
                    echo "New -" . $a . PHP_EOL;
                    echo "Old -" . $b . PHP_EOL;
                }
            } catch (\Throwable $th) {
                echo "Error checking $ssp revenues for $first_date => $last_date ".PHP_EOL;
            }
            try {


                $a = DB::connection('alternate')->select("select sum(impressions) as newImpressions from ssp_{$ssp} where date between '$first_date' AND '$last_date'")[0]->newImpressions;;
                $b = DB::connection('maindash')->select("select sum(" . $ssp_data['impressioni'] . ") as oldImpressions from {$ssp} where Data between '$first_date' AND '$last_date'")[0]->oldImpressions;;
                if ($a == $b) {
                    echo "$ssp impressions for '$first_date' AND '$last_date' are OK" . PHP_EOL;
                    echo "New -" . $a . PHP_EOL;
                    echo "Old -" . $b . PHP_EOL;
                } else {
                    $exception[]=[
                        "$ssp impressions for $first_date => $last_date are NOT OK" . PHP_EOL,
                        "New -" . $a . PHP_EOL,
                        "Old -" . $b . PHP_EOL,
                    ];
                    echo "$ssp impressions for '$first_date' AND '$last_date' are NOT OK" . PHP_EOL;
                    echo "New -" . $a . PHP_EOL;
                    echo "Old -" . $b . PHP_EOL;
                }
            }catch (\Throwable $th){
                echo "Error checking $ssp impressions for '$first_date' AND '$last_date'".PHP_EOL;
            }
        }
        if (count($exception)>0){
            throw new \Exception(print_r($exception, true));
        }
//        DB::connection('alternate')->select("select sum(requests) from ssp_ebda where date='$current_date'");
//        DB::connection('maindash')->select("select sum(Richieste) from ebda where Data='$current_date'");


    }
}
