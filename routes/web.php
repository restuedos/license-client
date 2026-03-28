<?php

use Edzero\LicenseClient\Http\Controllers\LicenseController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function (): void {
    Route::get('/license/activate', [LicenseController::class, 'show'])->name('license-client.activate.show');
    Route::post('/license/activate', [LicenseController::class, 'activate'])->name('license-client.activate.submit');
});
