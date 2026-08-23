<?php

declare(strict_types=1);

use App\Modules\Api\Http\Controllers\Web\ApiClientController;
use Illuminate\Support\Facades\Route;

/*
 * Machine credentials, managed by a person (API 4.2).
 */

Route::prefix('settings')->name('settings.')->group(function (): void {
    Route::get('/api-clients', [ApiClientController::class, 'index'])->name('api-clients');
    Route::post('/api-clients', [ApiClientController::class, 'store'])->name('api-clients.store');

    Route::patch('/api-clients/{client}', [ApiClientController::class, 'update'])->name('api-clients.update');
    Route::post('/api-clients/{client}/rotate', [ApiClientController::class, 'rotate'])->name('api-clients.rotate');
    Route::delete('/api-clients/{client}', [ApiClientController::class, 'revoke'])->name('api-clients.revoke');

    Route::delete('/api-tokens/{token}', [ApiClientController::class, 'revokeToken'])->name('api-tokens.revoke');
});
