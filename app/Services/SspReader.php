<?php

namespace App\Services;
use Carbon\Carbon;

class SspReader {


    public static function extractSsp(string $filename): string
    {
        return strtolower(preg_split('/[_]/', $filename)[0]);
    }

    public static function generateOutFileName(string $ssp, string $extension='csv', string $startDate, string $endDate): string
    {
        $outpath=storage_path('ssp');
        $adesso=Carbon::now()->toDateTimeString();
        return $outpath."/{$ssp}_{$adesso}_{$startDate}_{$endDate}.{$extension}";
    }

}
