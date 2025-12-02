<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;



Schedule::command('contratti:rinnovo-automatico')
    ->dailyAt('02:00')
    ->timezone('Europe/Rome');
