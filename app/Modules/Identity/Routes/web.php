<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\Web\CompanySwitchController;
use Illuminate\Support\Facades\Route;

/*
 * Authenticated routes under /app (Frontend 5).
 * Guest auth routes live in routes/web.php, outside the /app prefix.
 */

Route::middleware(['auth'])->group(function (): void {
    Route::post('/switch-company', [CompanySwitchController::class, 'store'])
        ->name('switch-company');
});
