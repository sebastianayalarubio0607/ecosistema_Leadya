<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');




Schedule::command('meta:sync-insights-yesterday --timezone=America/Bogota')
    ->dailyAt('02:00')
    ->timezone('America/Bogota');
