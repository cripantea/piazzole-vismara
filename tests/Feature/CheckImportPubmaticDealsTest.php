<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Modules\Importer\SspReader;
use Tests\TestCase;

uses(TestCase::class)->in(__DIR__);


it('imports and matches expected output pubmatic_domains', function () {

    $ssp='pubmatic_domains';
    $filename='pubmatic_domains.csv';
    $path = storage_path('testFiles');
    expect(true)->toBeTrue();
    $files = File::files($path);
    dump($filename);
    DB::table('ssp_pubmatic')->truncate();

    DB::table('ssp_pubmatic_deals')->truncate();

            $fullFileName = storage_path('testFiles/' . ($filename));

        expect(file_exists($fullFileName))->toBeTrue();


        $adesso = Carbon::now()->format('Y-m-d H:i:s');
        $extension = str_ends_with($filename, ".csv") ? "csv" : "json";
        $outFileName = "{$ssp}_{$adesso}_1900-01-01_1900-01-01.{$extension}";

        //copy($fullFileName, storage_path('ssp') . "/" . $outFileName);

        $class = "Modules\\Importer\\Ssp\\" . str_replace('_', '', ucfirst($ssp)) . "Manager";

        ////dump($class);
        $parser = app($class);
        $parser->import($fullFileName);
        $parser->import($fullFileName);

        $table = $parser->getTableName();
        $actualRows = DB::table($table)->get()->toArray();

        $expectedJsonFile = storage_path('testFiles/' . $ssp . "_db.json");
        $expectedJson = json_decode(file_get_contents($expectedJsonFile), true);

        expect($actualRows)->toHaveCount(count($expectedJson));


        $actualMap = [];

        foreach ($actualRows as $row) {
            $compositeKeyParts = [];

            foreach ($parser::$mapping as $db => $json) {
                $val = $row->$db ?? null;

                if (is_numeric($val)) {
                    // Normalize all numeric values to consistent format (e.g., 2 decimals)
                    $compositeKeyParts[] = number_format((float) $val, 2);
                } elseif (is_string($val)) {
                    // Normalize strings: trim spaces and convert to lowercase
                    $compositeKeyParts[] = strtolower(trim($val));
                } else {
                    // For other types (null, bool, etc.)
                    $compositeKeyParts[] = $val;
                }
            }

            $compositeKey = implode('|', $compositeKeyParts);
            $actualMap[$compositeKey] = true;
        }
        $missingExpectedRows = [];

        foreach ($expectedJson as $expectedRow) {
            $compositeKeyParts = [];

            foreach ($parser::$mapping as $db => $json) {
                $val = $row->$db ?? null;

                if (is_numeric($val)) {
                    // Normalize all numeric values to consistent format (e.g., 2 decimals)
                    $compositeKeyParts[] = number_format((float) $val, 2);
                } elseif (is_string($val)) {
                    // Normalize strings: trim spaces and convert to lowercase
                    $compositeKeyParts[] = strtolower(trim($val));
                } else {
                    // For other types (null, bool, etc.)
                    $compositeKeyParts[] = $val;
                }
            }

            $compositeKey = implode('|', $compositeKeyParts);

            if (!isset($actualMap[$compositeKey])) {
                $missingExpectedRows[] = $expectedRow;
            }
        }

        //dump(print_r($missingExpectedRows, true));

        expect($missingExpectedRows)->toBeEmpty("Some expected rows were not found in the actual data. expectedJson => ActualRows");

        $expectedLookup = [];

        foreach ($expectedJson as $expectedRow) {
            $compositeKeyParts = [];

            foreach ($parser::$mapping as $db => $json) {
                $val = is_array($expectedRow) ? $expectedRow[$json] : $expectedRow->$json;

                if (is_numeric($val)) {
                    $val = number_format((float)$val, 2);
                } elseif (is_string($val)) {
                    $val = strtolower(trim($val));
                }

                $compositeKeyParts[] = $val;
            }

            $key = implode('|', $compositeKeyParts);
            $expectedLookup[$key] = true;
        }

        $missingExpectedRows = [];


    foreach ($actualRows as $row) {
        $compositeKeyParts = [];

        foreach ($parser::$mapping as $db => $json) {
            $val = $row->$db ?? null;

            if (is_numeric($val)) {
                $val = number_format((float)$val, 2);
            } elseif (is_string($val)) {
                $val = strtolower(trim($val));
            }

            $compositeKeyParts[] = $val;
        }

        $key = implode('|', $compositeKeyParts);

        if (!isset($expectedLookup[$key])) {
            $missingExpectedRows[] = $row;
        }
    }
        //dump(print_r($missingExpectedRows, true));
        expect($missingExpectedRows)->toBeEmpty("Some expected rows were not found in the actual data. expectedJson <= ActualRows");

    $waitDirectory = storage_path('testFiles/wait');

    if (!File::isDirectory($waitDirectory)) {
        File::makeDirectory($waitDirectory, 0755, true, true);
    }

    $newFilePath = $waitDirectory . '/' . $filename;
    $jsonFile=$ssp . "_db.json";
    $newJsonFilePath = $waitDirectory . '/' . $jsonFile;

    // Move the file from its original location to the 'wait' directory.
    File::move($fullFileName, $newFilePath);
    File::move($expectedJsonFile, $newJsonFilePath);

    // You can add a dump to confirm the file has been moved
    dump("File moved: " . $fullFileName . " -> " . $newFilePath);


});






it('imports and matches expected output pubmatic deals', function () {

    $ssp='pubmatic_deals';
    $filename='pubmatic_deals.xls';
    $path = storage_path('testFiles');
    expect(true)->toBeTrue();
    $files = File::files($path);
        dump($filename);
        $fullFileName = storage_path('testFiles/' . ($filename));

        expect(file_exists($fullFileName))->toBeTrue();
        $adesso = Carbon::now()->format('Y-m-d H:i:s');
        $extension = str_ends_with($filename, ".csv") ? "csv" : "json";
        $outFileName = "{$ssp}_{$adesso}_1900-01-01_1900-01-01.{$extension}";
        DB::table('ssp_pubmatic')->truncate();

        DB::table('ssp_pubmatic_deals')->truncate();

        //copy($fullFileName, storage_path('ssp') . "/" . $outFileName);

        $class = "Modules\\Importer\\Ssp\\" . str_replace('_', '', ucfirst($ssp)) . "Manager";

        ////dump($class);
        $parser = app($class);
        $parser->import($fullFileName);
        $parser->import($fullFileName);

        $table = $parser->getTableName();
        $actualPubmaticRows = DB::table('ssp_pubmatic')->where('report_code', 'deals report')->get()->toArray();
        $actualDealsRows = DB::table('ssp_pubmatic_deals')->get()->toArray();

        $expectedpubmaticJsonFile = storage_path('testFiles/pubmatic_deals_pubmatic_db.json');
        $expectedpubmaticdealsJsonFile = storage_path('testFiles/pubmatic_deals_db.json');
        $expectedpubmaticJson = json_decode(file_get_contents($expectedpubmaticJsonFile), true);
        $expectedpubmaticdealsJson = json_decode(file_get_contents($expectedpubmaticdealsJsonFile), true);

        //$expectedJson = array_merge($expectedpub1Json, $expectedpub2Json);

        //expect($actualRows)->toHaveCount(count($expectedJson));
        //DB::table('pubmatic')->truncate();

        $actualMap = [];

        foreach ($actualPubmaticRows as $row) {
            $compositeKeyParts = [];

            foreach (Modules\Importer\Ssp\PubmaticdomainsManager::$mapping as $db => $json) {
                $val = $row->$db ?? null;

                if (is_numeric($val)) {
                    // Normalize all numeric values to consistent format (e.g., 2 decimals)
                    $compositeKeyParts[] = number_format((float) $val, 2);
                } elseif (is_string($val)) {
                    // Normalize strings: trim spaces and convert to lowercase
                    $compositeKeyParts[] = strtolower(trim($val));
                } else {
                    // For other types (null, bool, etc.)
                    $compositeKeyParts[] = $val;
                }
            }

            $compositeKey = implode('|', $compositeKeyParts);
            $actualMap[$compositeKey] = true;
        }
        $missingExpectedRows = [];

        foreach ($expectedpubmaticJson as $expectedRow) {
            $compositeKeyParts = [];

            foreach (Modules\Importer\Ssp\PubmaticdomainsManager::$mapping as $db => $json) {
                $val = $row->$db ?? null;

                if (is_numeric($val)) {
                    // Normalize all numeric values to consistent format (e.g., 2 decimals)
                    $compositeKeyParts[] = number_format((float) $val, 2);
                } elseif (is_string($val)) {
                    // Normalize strings: trim spaces and convert to lowercase
                    $compositeKeyParts[] = strtolower(trim($val));
                } else {
                    // For other types (null, bool, etc.)
                    $compositeKeyParts[] = $val;
                }
            }

            $compositeKey = implode('|', $compositeKeyParts);

            if (!isset($actualMap[$compositeKey])) {
                $missingExpectedRows[] = $expectedRow;
            }
        }

        //dump(print_r($missingExpectedRows, true));

        expect($missingExpectedRows)->toBeEmpty("Some expected rows were not found in the actual data. expectedJson => ActualRows");

        $expectedLookup = [];

        foreach ($expectedpubmaticJson as $expectedRow) {
            $compositeKeyParts = [];

            foreach (Modules\Importer\Ssp\PubmaticdomainsManager::$mapping as $db => $json) {
                $val = is_array($expectedRow) ? $expectedRow[$json] : $expectedRow->$json;

                if (is_numeric($val)) {
                    $val = number_format((float)$val, 2);
                } elseif (is_string($val)) {
                    $val = strtolower(trim($val));
                }

                $compositeKeyParts[] = $val;
            }

            $key = implode('|', $compositeKeyParts);
            $expectedLookup[$key] = true;
        }

        $missingExpectedRows = [];


foreach ($actualPubmaticRows as $row) {
    $compositeKeyParts = [];

    foreach (Modules\Importer\Ssp\PubmaticdomainsManager::$mapping as $db => $json) {
        $val = $row->$db ?? null;

        if (is_numeric($val)) {
            $val = number_format((float)$val, 2);
        } elseif (is_string($val)) {
            $val = strtolower(trim($val));
        }

        $compositeKeyParts[] = $val;
    }

    $key = implode('|', $compositeKeyParts);

    if (!isset($expectedLookup[$key])) {
        $missingExpectedRows[] = $row;
    }
}

        expect($missingExpectedRows)->toBeEmpty("Some expected rows were not found in the actual data. expectedJson <= ActualRows");


        /* --------------------------- Deals -------*/


/*
        $actualDealsRows = DB::table('ssp_pubmatic_deals')->get()->toArray();


        $expectedpubmaticdealsJson = json_decode(file_get_contents($expectedpubmaticdealsJsonFile), true);


*/

        $actualMap = [];

        foreach ($actualDealsRows as $row) {
            $compositeKeyParts = [];

            foreach (Modules\Importer\Ssp\PubmaticdealsManager::$mapping as $db => $json) {
                $val = $row->$db ?? null;

                if (is_numeric($val)) {
                    // Normalize all numeric values to consistent format (e.g., 2 decimals)
                    $compositeKeyParts[] = number_format((float) $val, 2);
                } elseif (is_string($val)) {
                    // Normalize strings: trim spaces and convert to lowercase
                    $compositeKeyParts[] = strtolower(trim($val));
                } else {
                    // For other types (null, bool, etc.)
                    $compositeKeyParts[] = $val;
                }
            }

            $compositeKey = implode('|', $compositeKeyParts);
            $actualMap[$compositeKey] = true;
        }
        $missingExpectedRows = [];

        foreach ($expectedpubmaticdealsJson as $expectedRow) {
            $compositeKeyParts = [];

            foreach (Modules\Importer\Ssp\PubmaticdealsManager::$mapping as $db => $json) {
                $val = $row->$db ?? null;

                if (is_numeric($val)) {
                    // Normalize all numeric values to consistent format (e.g., 2 decimals)
                    $compositeKeyParts[] = number_format((float) $val, 2);
                } elseif (is_string($val)) {
                    // Normalize strings: trim spaces and convert to lowercase
                    $compositeKeyParts[] = strtolower(trim($val));
                } else {
                    // For other types (null, bool, etc.)
                    $compositeKeyParts[] = $val;
                }
            }

            $compositeKey = implode('|', $compositeKeyParts);

            if (!isset($actualMap[$compositeKey])) {
                $missingExpectedRows[] = $expectedRow;
            }
        }

        //dump(print_r($missingExpectedRows, true));

        expect($missingExpectedRows)->toBeEmpty("Some expected rows were not found in the actual data. expectedJson => ActualRows");

        $expectedLookup = [];

        foreach ($expectedpubmaticdealsJson as $expectedRow) {
            $compositeKeyParts = [];

            foreach (Modules\Importer\Ssp\PubmaticdealsManager::$mapping as $db => $json) {
                $val = is_array($expectedRow) ? $expectedRow[$json] : $expectedRow->$json;

                if (is_numeric($val)) {
                    $val = number_format((float)$val, 2);
                } elseif (is_string($val)) {
                    $val = strtolower(trim($val));
                }

                $compositeKeyParts[] = $val;
            }

            $key = implode('|', $compositeKeyParts);
            $expectedLookup[$key] = true;
        }

        $missingExpectedRows = [];


foreach ($actualDealsRows as $row) {
    $compositeKeyParts = [];

    foreach (Modules\Importer\Ssp\PubmaticdealsManager::$mapping as $db => $json) {
        $val = $row->$db ?? null;

        if (is_numeric($val)) {
            $val = number_format((float)$val, 2);
        } elseif (is_string($val)) {
            $val = strtolower(trim($val));
        }

        $compositeKeyParts[] = $val;
    }

    $key = implode('|', $compositeKeyParts);

    if (!isset($expectedLookup[$key])) {
        $missingExpectedRows[] = $row;
    }
}

        expect($missingExpectedRows)->toBeEmpty("Some expected rows were not found in the actual data. expectedJson <= ActualRows");
/*----------- end deals ------*/

    $waitDirectory = storage_path('testFiles/wait');

    // Create the 'wait' directory if it doesn't exist.
    if (!File::isDirectory($waitDirectory)) {
        File::makeDirectory($waitDirectory, 0755, true, true);
    }

    $newFilePath = $waitDirectory . '/' . $filename;
    $jsonFile1="pubmatic_deals_db.json";
    $jsonFile2="pubmatic_deals_pubmatic_db.json";
    $newJsonFilePath1 = $waitDirectory . '/' . $jsonFile1;
    $newJsonFilePath2 = $waitDirectory . '/' . $jsonFile2;

    // Move the file from its original location to the 'wait' directory.
    File::move($fullFileName, $newFilePath);
    File::move($expectedpubmaticJsonFile, $newJsonFilePath1);
    File::move($expectedpubmaticdealsJsonFile, $newJsonFilePath2);

    // You can add a dump to confirm the file has been moved
    dump("File moved: " . $fullFileName . " -> " . $newFilePath);


});
