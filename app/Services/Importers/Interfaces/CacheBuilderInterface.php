<?php

namespace App\Services\Importers\Interfaces;

use Carbon\CarbonImmutable;

interface CacheBuilderInterface
{
    public function getDomainCache(?CarbonImmutable $current_date): array;


}
