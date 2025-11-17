<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;



Schedule::command('dash:import-ssp')
    ->hourly();

Schedule::command('dash:check-import-data')
    ->daily()->at('08:00');
