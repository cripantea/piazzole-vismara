<?php

namespace App\Services\Importers\Parsers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\Importers\Interfaces\SspParserInterface;

abstract class JsonParser extends BaseParser
{

    public function getClassName(): string
    {
        $reflection = new \ReflectionClass($this);
        return $reflection->getShortName();
    }

    /**
     * @throws \JsonException
     */
    public function ndjsonToJson(string $ndjson): string {
        $items = [];
        foreach (preg_split("/\r\n|\n|\r/", $ndjson) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $items[] = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        }
        return json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @throws \JsonException
     */
    public function parse(string $filename): array
    {
        $ssp=get_class($this);
        Log::info(" Start parsing {$filename} {$ssp}");
        $jsonContent = file_get_contents($filename);
        if($this->getClassName() === "AmazonManager"){
            $jsonContent=$this->ndjsonToJson($jsonContent);
        }

        $jsonArray = json_decode($jsonContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON string: " . json_last_error_msg());
        }
        $numRows = count($jsonArray);
        Log::info("{count($numRows)} rows parsed ");
        return $jsonArray;

    }


}
