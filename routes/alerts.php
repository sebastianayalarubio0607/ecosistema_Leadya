<?php

use App\Http\Controllers\Alerts\AlertController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('alerts')->name('alerts.')->group(function () {
    Route::get('/', [AlertController::class, 'index'])->name('index');
    Route::get('/rules', [AlertController::class, 'rules'])->name('rules');
    Route::get('/history', [AlertController::class, 'history'])->name('history');
    Route::get('/metrics', [\App\Http\Controllers\Alerts\MetricMonitorController::class, 'index'])->name('metrics');
});
