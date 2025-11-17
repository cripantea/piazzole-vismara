<?php
namespace App\Services\Importers\Interfaces;

interface SspDownloadInterface{
    public static function downloadFromAPI(?string $fromDate, ?string $toDate, ?string $fileName);
}
