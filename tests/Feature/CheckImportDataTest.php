<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Modules\Importer\SspReader;
use Tests\TestCase;
//ini_set('memory_limit', '2G');
//uses(TestCase::class)->in(__DIR__);

function checkAndUnzipFiles(){
    // da scrivere
}


beforeAll(function () {

    dump("sono in beforeall");

});

dataset('files', function () {
    dump("sono in dataset");

    $app=require __DIR__."/../../bootstrap/app.php";
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    // Define the list of files to be excluded from the test suite.
    $filesToSkip = [
        'appnexus_adunits.csv',
        'appnexus_curated_list.csv',
        'google_historical.csv',
        'pubmatic_deals.xls',
        'pubmatic_domains.csv'
    ];

    return collect(File::files(storage_path('testFiles')))
        ->filter(fn($f) =>
            (str_ends_with($f->getFilename(), '.csv') || str_ends_with($f->getFilename(), '.xls') || str_ends_with($f->getFilename(), '.xlsx') || str_ends_with($f->getFilename(), '.json'))
            && !str_ends_with($f->getFilename(), '_db.json')
            // Add the new condition to exclude files from the list.
            && !in_array($f->getFilename(), $filesToSkip)
        )
        ->map(fn($f) => [
            str_starts_with($f->getFilename(), 'msn_')
                ? 'msn'
                : explode('.', $f->getFilename())[0],
            $f->getFilename(),
        ])
        ->toArray();
});

it('imports and matches expected output for each SSP file', function ($ssp, $filename) {
    $filesToSkip = [
        'apnexus_adunits.csv',
        'another_file.csv',
        'yet_another_file.json',
    ];

    // Skip the file if its name is in the list
    if (in_array($filename, $filesToSkip)) {
        return;
    }


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

        $class = "App\\Services\\Importers\\Ssp\\" . str_replace('_', '', ucfirst($ssp)) . "Manager";

        ////dump($class);
        $parser = app($class);
        $parser->import($fullFileName);
        $parser->import($fullFileName);

        $table = $parser->getTableName();
        $actualRows = DB::connection('alternate')->table($table)->get()->toArray();

        $expectedJsonFile = storage_path('testFiles/' . $ssp . "_db.json");
        $expectedJson = json_decode(file_get_contents($expectedJsonFile), true);

        //expect($actualRows)->toHaveCount(count($expectedJson));


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

dump(print_r($missingExpectedRows, true));
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

       dump(print_r($missingExpectedRows, true));

        expect($missingExpectedRows)->toBeEmpty("Some expected rows were not found in the actual data. expectedJson <= ActualRows");

    // $waitDirectory = storage_path('testFiles/wait');

    // // Create the 'wait' directory if it doesn't exist.
    // if (!File::isDirectory($waitDirectory)) {
    //     File::makeDirectory($waitDirectory, 0755, true, true);
    // }

    // $newFilePath = $waitDirectory . '/' . $filename;
    // $jsonFile=$ssp . "_db.json";
    // $newJsonFilePath = $waitDirectory . '/' . $jsonFile;

    // // Move the file from its original location to the 'wait' directory.
    // File::move($fullFileName, $newFilePath);
    // File::move($expectedJsonFile, $newJsonFilePath);

    // // You can add a dump to confirm the file has been moved
    // dump("File moved: " . $fullFileName . " -> " . $newFilePath);


})->with('files');
