<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\UrlGeneratorController;

Route::get('/generate-url', [UrlGeneratorController::class, 'showForm']);
Route::post('/generate-url', [UrlGeneratorController::class, 'generateUrl'])->name('generate-url');

Route::view('/', 'welcome');
 

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
