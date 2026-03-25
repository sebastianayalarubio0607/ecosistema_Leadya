<?php

use App\Jobs\RefreshMetaLongLivedTokenJob;
use App\Jobs\SyncMetaFormsJob;
use App\Jobs\SyncMetaLeadsJob;
use App\Jobs\SyncMetaPagesJob;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');




Schedule::command('meta:sync-insights-yesterday --timezone=America/Bogota')
    ->dailyAt('02:00')
    ->timezone('America/Bogota');

Schedule::job(new RefreshMetaLongLivedTokenJob())
    ->hourly()
    ->timezone('America/Bogota')
    ->withoutOverlapping();

Schedule::job(new SyncMetaPagesJob())
    ->hourly()
    ->timezone('America/Bogota')
    ->withoutOverlapping();

Schedule::job(new SyncMetaFormsJob())
    ->hourly()
    ->timezone('America/Bogota')
    ->withoutOverlapping();

Schedule::job(new SyncMetaLeadsJob())
    ->hourly()
    ->timezone('America/Bogota')
    ->withoutOverlapping();
