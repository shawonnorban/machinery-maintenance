<?php

declare(strict_types=1);

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

// Placeholder until the dashboard module lands (build order step 26).
Route::get('/app/dashboard', fn () => view('identity::auth.dashboard-placeholder'))
    ->middleware(['auth'])
    ->name('app.dashboard');
