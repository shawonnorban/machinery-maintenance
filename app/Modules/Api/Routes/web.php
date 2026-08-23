<?php

declare(strict_types=1);

use App\Modules\Api\Http\Controllers\Web\ApiClientController;
use App\Modules\Api\Http\Controllers\Web\SessionTokenController;
use Illuminate\Support\Facades\Route;

/*
 * The web side of the API: credentials a person manages, and the token the
 * page itself uses.
 *
 * `auth` is applied here rather than assumed. Module route files carry their
 * own, and a file that forgets it publishes its screens to the internet.
 */

Route::middleware('auth')->group(function (): void {
    /*
     * The token the offline queue posts with (SRS 38). Session authenticated
     * and CSRF protected like any other web route, which is what makes it safe
     * to hand a bearer token to a browser at all.
     */
    Route::post('/session-token', [SessionTokenController::class, 'store'])->name('session-token');

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
});
