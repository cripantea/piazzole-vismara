<?php

namespace App\Services\Importers\Parsers;

use App\Models\SspAniview;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Importer\DTOs\AniviewDTO;
use App\Services\Importers\Interfaces\SspParserInterface;

abstract class ExcelParser extends BaseParser
{

    abstract function getDelimiter():string;
    abstract function parse(string $filename): array;
    public function getEndDelimiter(): string
    {
        return '';
    }

}
