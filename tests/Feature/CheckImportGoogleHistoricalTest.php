<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Modules\Importer\SspReader;
use Tests\TestCase;
ini_set('memory_limit', '2G');
uses(TestCase::class)->in(__DIR__);


it('imports and matches google_historical', function () {

    $ssp='google_historical';
    $filename='google_historical.csv';
    $path = storage_path('testFiles');
    expect(true)->toBeTrue();
    $files = File::files($path);
    dump($filename);
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

        $dates = DB::table('ssp_data_google')
            ->select('date')
            ->distinct()
            ->pluck('date');


        // $table = $parser->getTableName();
        // $actualRows = DB::table('ssp_data_google')->get()->toArray();

        $expectedJsonFile = storage_path('testFiles/' . $ssp . "_db.json");
        $expectedJson = json_decode(file_get_contents($expectedJsonFile), true);
        $expectedByDate = collect($expectedJson)->groupBy('Data');

        //expect($actualRows)->toHaveCount(count($expectedJson));

        foreach ($dates as $date) {
            $actualRowsForDate = DB::table('ssp_data_google')
                ->where('date', $date)
                ->get()
                ->toArray();

            $expectedRowsForDate = $expectedByDate->get($date);

            if ($expectedRowsForDate === null) {
                continue;
            }
/* start test */

            $actualMap = [];

            foreach ($actualRowsForDate as $row) {
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

            foreach ($expectedRowsForDate as $expectedRow) {
                $compositeKeyParts = [];

                foreach ($parser::$mapping as $db => $json) {
                    $val = $expectedRow[$json] ?? null;

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

            foreach ($expectedRowsForDate as $expectedRow) {
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


            foreach ($actualRowsForDate as $row) {
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

            expect($missingExpectedRows)->toBeEmpty("Some expected rows were not found in the actual data. expectedJson <= ActualRows");
/* end test */
        }

    $waitDirectory = storage_path('testFiles/wait');

    // Create the 'wait' directory if it doesn't exist.
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



// it('imports and matches expected output for appnexus_adunits', function () {

//     $ssp='appnexus_adunits';
//     $filename='appnexus_adunits.csv';
//     $path = storage_path('testFiles');
//     expect(true)->toBeTrue();
//     $files = File::files($path);
//     dump($filename);
//         $fullFileName = storage_path('testFiles/' . ($filename));

//         expect(file_exists($fullFileName))->toBeTrue();


//         $adesso = Carbon::now()->format('Y-m-d H:i:s');
//         $extension = str_ends_with($filename, ".csv") ? "csv" : "json";
//         $outFileName = "{$ssp}_{$adesso}_1900-01-01_1900-01-01.{$extension}";

//         //copy($fullFileName, storage_path('ssp') . "/" . $outFileName);

//         $class = "Modules\\Importer\\Ssp\\" . str_replace('_', '', ucfirst($ssp)) . "Manager";

//         ////dump($class);
//         $parser = app($class);
//         $parser->import($fullFileName);
//         $parser->import($fullFileName);

//         $table = $parser->getTableName();

//         $actualAdunits = DB::table('ssp_appnexus')->get()->toArray();
//         $actualCurated = DB::table('ssp_appnexus_curated')->get()->toArray();

//         $expectedCuratedJson = json_decode(file_get_contents(storage_path('testFiles/appnexus_adunits_appnexus_curated_db.json')), true);
//         $expectedApsJson = json_decode(file_get_contents(storage_path('testFiles/appnexus_adunits_appnexus_db.json')), true);


// $actualMap = [];

// foreach ($actualAdunits as $row) {
//     $compositeKeyParts = [];

//     foreach ($parser::$mapping as $db => $json) {
//         $val = $row->$db ?? null;

//         if (is_numeric($val)) {
//             // Normalize all numeric values to consistent format (e.g., 2 decimals)
//             $compositeKeyParts[] = number_format((float) $val, 2);
//         } elseif (is_string($val)) {
//             // Normalize strings: trim spaces and convert to lowercase
//             $compositeKeyParts[] = strtolower(trim($val));
//         } else {
//             // For other types (null, bool, etc.)
//             $compositeKeyParts[] = $val;
//         }
//     }

//     $compositeKey = implode('|', $compositeKeyParts);
//     $actualMap[$compositeKey] = true;
// }

// $missingExpectedRows = [];

// foreach ($expectedApsJson as $expectedRow) {
//     $compositeKeyParts = [];

//     foreach ($parser::$mapping as $db => $json) {
//         $val = $row->$db ?? null;

//         if (is_numeric($val)) {
//             // Normalize all numeric values to consistent format (e.g., 2 decimals)
//             $compositeKeyParts[] = number_format((float) $val, 2);
//         } elseif (is_string($val)) {
//             // Normalize strings: trim spaces and convert to lowercase
//             $compositeKeyParts[] = strtolower(trim($val));
//         } else {
//             // For other types (null, bool, etc.)
//             $compositeKeyParts[] = $val;
//         }
//     }

//     $compositeKey = implode('|', $compositeKeyParts);

//     if (!isset($actualMap[$compositeKey])) {
//         $missingExpectedRows[] = $expectedRow;
//     }
// }

// //dump(print_r($missingExpectedRows, true));
//         expect($missingExpectedRows)->toBeEmpty("Some expected rows were not found in the actual data. expectedJson => ActualRows");
// dump("actual incluso in  previsti adunit ");
// $expectedLookup = [];

// foreach ($expectedApsJson as $expectedRow) {
//     $compositeKeyParts = [];

//     foreach ($parser::$mapping as $db => $json) {
//         $val = is_array($expectedRow) ? $expectedRow[$json] : $expectedRow->$json;

//         if (is_numeric($val)) {
//             $val = number_format((float)$val, 2);
//         } elseif (is_string($val)) {
//             $val = strtolower(trim($val));
//         }

//         $compositeKeyParts[] = $val;
//     }

//     $key = implode('|', $compositeKeyParts);
//     $expectedLookup[$key] = true;
// }

//         $missingExpectedRows = [];


// foreach ($actualAdunits as $row) {
//     $compositeKeyParts = [];

//     foreach ($parser::$mapping as $db => $json) {
//         $val = $row->$db ?? null;

//         if (is_numeric($val)) {
//             $val = number_format((float)$val, 2);
//         } elseif (is_string($val)) {
//             $val = strtolower(trim($val));
//         }

//         $compositeKeyParts[] = $val;
//     }

//     $key = implode('|', $compositeKeyParts);

//     if (!isset($expectedLookup[$key])) {
//         $missingExpectedRows[] = $row;
//     }
// }

// //        dump(print_r($missingExpectedRows, true));

//         expect($missingExpectedRows)->toBeEmpty("Some expected rows were not found in the actual data. expectedJson <= ActualRows");
// dump("previsti incluso in actual adunit ");

//         // ----------- CURATED ----------------

// $actualMap = [];

// foreach ($actualCurated as $row) {
//     $compositeKeyParts = [];

//     foreach ($parser::$mapping as $db => $json) {
//         $val = $row->$db ?? null;

//         if (is_numeric($val)) {
//             // Normalize all numeric values to consistent format (e.g., 2 decimals)
//             $compositeKeyParts[] = number_format((float) $val, 2);
//         } elseif (is_string($val)) {
//             // Normalize strings: trim spaces and convert to lowercase
//             $compositeKeyParts[] = strtolower(trim($val));
//         } else {
//             // For other types (null, bool, etc.)
//             $compositeKeyParts[] = $val;
//         }
//     }

//     $compositeKey = implode('|', $compositeKeyParts);
//     $actualMap[$compositeKey] = true;
// }

// $missingExpectedRows = [];

// foreach ($expectedCuratedJson as $expectedRow) {
//     $compositeKeyParts = [];

//     foreach ($parser::$mapping as $db => $json) {
//         $val = $row->$db ?? null;

//         if (is_numeric($val)) {
//             // Normalize all numeric values to consistent format (e.g., 2 decimals)
//             $compositeKeyParts[] = number_format((float) $val, 2);
//         } elseif (is_string($val)) {
//             // Normalize strings: trim spaces and convert to lowercase
//             $compositeKeyParts[] = strtolower(trim($val));
//         } else {
//             // For other types (null, bool, etc.)
//             $compositeKeyParts[] = $val;
//         }
//     }

//     $compositeKey = implode('|', $compositeKeyParts);

//     if (!isset($actualMap[$compositeKey])) {
//         $missingExpectedRows[] = $expectedRow;
//     }
// }

// //dump(print_r($missingExpectedRows, true));
//         expect($missingExpectedRows)->toBeEmpty("Some expected rows were not found in the actual data. expectedJson => ActualRows");

//         dump("actual incluso in previsti curated ");

// $expectedLookup = [];

// foreach ($expectedCuratedJson as $expectedRow) {
//     $compositeKeyParts = [];

//     foreach ($parser::$mapping as $db => $json) {
//         $val = is_array($expectedRow) ? $expectedRow[$json] : $expectedRow->$json;

//         if (is_numeric($val)) {
//             $val = number_format((float)$val, 2);
//         } elseif (is_string($val)) {
//             $val = strtolower(trim($val));
//         }

//         $compositeKeyParts[] = $val;
//     }

//     $key = implode('|', $compositeKeyParts);
//     $expectedLookup[$key] = true;
// }

//         $missingExpectedRows = [];


// foreach ($actualCurated as $row) {
//     $compositeKeyParts = [];

//     foreach ($parser::$mapping as $db => $json) {
//         $val = $row->$db ?? null;

//         if (is_numeric($val)) {
//             $val = number_format((float)$val, 2);
//         } elseif (is_string($val)) {
//             $val = strtolower(trim($val));
//         }

//         $compositeKeyParts[] = $val;
//     }

//     $key = implode('|', $compositeKeyParts);

//     if (!isset($expectedLookup[$key])) {
//         $missingExpectedRows[] = $row;
//     }
// }


//         expect($missingExpectedRows)->toBeEmpty("Some expected rows were not found in the actual data. expectedJson <= ActualRows");
// dump("previsti incluso in actual curated ");





//     // $waitDirectory = storage_path('testFiles/wait');

//     // // Create the 'wait' directory if it doesn't exist.
//     // if (!File::isDirectory($waitDirectory)) {
//     //     File::makeDirectory($waitDirectory, 0755, true, true);
//     // }

//     // $newFilePath = $waitDirectory . '/' . $filename;
//     // $jsonFile=$ssp . "_db.json";
//     // $newJsonFilePath = $waitDirectory . '/' . $jsonFile;

//     // // Move the file from its original location to the 'wait' directory.
//     // File::move($fullFileName, $newFilePath);
//     // File::move($expectedJsonFile, $newJsonFilePath);

//     // // You can add a dump to confirm the file has been moved
//     // dump("File moved: " . $fullFileName . " -> " . $newFilePath);


// });
