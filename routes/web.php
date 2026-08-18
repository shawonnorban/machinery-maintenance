<?php

declare(strict_types=1);

use App\Modules\Asset\Http\Controllers\Web\ScanController;
use App\Modules\Identity\Http\Controllers\Web\LoginController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/app/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
 * The QR landing routes (Data Dictionary 5.2). Outside the /app prefix so a
 * printed label stays short, and behind auth so a scanned token alone grants
 * nothing: a guest is sent to login and returned here afterwards.
 */
Route::middleware('auth')->group(function (): void {
    Route::get('/s/l/{code}', [ScanController::class, 'location'])->name('scan.location');
    Route::get('/s/{code}', [ScanController::class, 'asset'])->name('scan.asset');
});
