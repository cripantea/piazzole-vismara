<?php

namespace App\Services\Importers\Interfaces;

use Carbon\CarbonImmutable;

interface SspParserInterface {

    public function parse(string $filePath): array;

    public function getDtoClass():string;

}
