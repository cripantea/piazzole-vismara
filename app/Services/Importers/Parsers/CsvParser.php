<?php

namespace App\Services\Importers\Parsers;

use App\Models\SspAniview;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Importer\DTOs\AniviewDTO;
use App\Services\Importers\Interfaces\SspParserInterface;

abstract class CsvParser extends BaseParser
{

    abstract function getDelimiter():string;
    public function getEndDelimiter(): string
    {
        return '';
    }
    function cleanCsvText($text) {

        //$text= mb_convert_encoding($text, 'UTF-8', 'UTF-16LE');
        $text = preg_replace([
                '/^\xFF\xFE/',
                '/\x00/'
            ], '', $text);

        //$text = preg_replace('/[^\P{C}\t\r\n]+/u', '', $text);

        return $text;
    }
    public function parse(string $filename): array
    {
        $ssp=get_class($this);
        Log::info(" Start parsing csv {$filename} {$ssp}");

        $handle = fopen($filename, 'r');
        if (! $handle) {
            throw new \RuntimeException("Cannot open file: $filename");
        }

        $startParsing = false;
        $header = [];
        $rows = [];

        while (($line = fgets($handle)) !== false) {

            //$line = mb_convert_encoding($line, 'UTF-8', 'UTF-16LE');//$this->cleanCsvText($line);
            $line=$this->cleanCsvText($line);
            if (! $startParsing) {
                if (Str::contains($line, $this->getDelimiter())) {
                    $header = str_getcsv($line, $this->getCsvDelimiter());
                    $startParsing = true;
                }
                continue;
            }

            if ($line === '' || Str::contains($line, $this->getEndDelimiter())) {
                continue;
            }

            $values = str_getcsv($line, $this->getCsvDelimiter());
            $rows[] = $values;
        }
        $numRows = count($rows);
        Log::info("{count($numRows)} rows parsed ");

        fclose($handle);

        return [
            'header' => $header,
            'rows' => $rows,
        ];
    }


}
