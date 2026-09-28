<?php

declare(strict_types=1);

use App\Modules\Metering\Http\Controllers\Web\MeterController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 * Meters and their readings (SRS 11).
 *
 * The half of usage-based maintenance that was missing: a plan can say
 * "service every 500 running hours", and until somebody records the hours it
 * can never come due.
 */
Route::middleware('auth')->group(function (): void {
    // Superseded by the Next.js screens at /metering (docs/12-Stack-
    // Migration-Implementation-Plan.md Phase H) — full parity confirmed
    // (record reading, replace/reset, reading history all present there).
    // Redirecting rather than deleting keeps old bookmarks/links working
    // during the module's soak period; the POST action routes below stay
    // as-is since nothing links to them directly, only forms submit to them.
    Route::get('/meters', function (Request $request) {
        $assetId = $request->query('asset_id');

        return redirect('/metering'.($assetId ? '?'.http_build_query(['asset_id' => $assetId]) : ''));
    })->name('meters.index');
    Route::get('/meters/{meter}', fn ($meter) => redirect("/metering/{$meter}"))->name('meters.show');

    // Recording is the technician's job and needs only their permission;
    // fitting and resetting a meter are configuration.
    Route::post('/meters/{meter}/readings', [MeterController::class, 'record'])->name('meters.readings');
    Route::post('/meters/{meter}/reset', [MeterController::class, 'reset'])->name('meters.reset');
    Route::post('/meters/{meter}/toggle', [MeterController::class, 'toggle'])->name('meters.toggle');

    Route::post('/assets/{asset}/meters', [MeterController::class, 'attach'])->name('assets.meters.attach');
});
