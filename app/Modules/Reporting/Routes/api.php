<?php

declare(strict_types=1);

use App\Modules\Reporting\Http\Controllers\Api\ExportApiController;
use App\Modules\Reporting\Http\Controllers\Api\ImportApiController;
use App\Modules\Reporting\Http\Controllers\Api\ReportApiController;
use App\Modules\Reporting\Http\Controllers\Api\ReportJobApiController;
use Illuminate\Support\Facades\Route;

/*
 * Reports (API 22), imports (API 23) and raw-data exports (API 24).
 *
 * `GET /reports/{key}` is one generic preview endpoint over
 * `ReportRegistry`'s eighteen reports, not one route per report — v1.0's
 * spec names a handful of reports as `/reports/assets`, `/reports/costs`
 * and so on, but the report keys those would have to map onto
 * (`asset_register`, `maintenance_cost`, ...) already are the URL segment
 * here. See ReportApiController's docblock.
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/reports', [ReportApiController::class, 'index'])->name('reports.index');
    Route::get('/reports/{key}', [ReportApiController::class, 'show'])->name('reports.show');

    Route::get('/report-jobs', [ReportJobApiController::class, 'index'])->name('report-jobs.index');
    Route::post('/report-jobs', [ReportJobApiController::class, 'store'])->name('report-jobs.store');
    Route::get('/report-jobs/{job}', [ReportJobApiController::class, 'show'])->name('report-jobs.show');
    Route::get('/report-jobs/{job}/download', [ReportJobApiController::class, 'download'])
        ->name('report-jobs.download');

    Route::get('/imports', [ImportApiController::class, 'index'])->name('imports.index');
    Route::get('/imports/{type}/template', [ImportApiController::class, 'template'])
        ->name('imports.template');
    Route::post('/imports/{type}', [ImportApiController::class, 'store'])->name('imports.store');
    Route::get('/imports/{job}', [ImportApiController::class, 'show'])->name('imports.show');
    Route::get('/imports/{job}/errors', [ImportApiController::class, 'errors'])->name('imports.errors');
    Route::post('/imports/{job}/confirm', [ImportApiController::class, 'confirm'])->name('imports.confirm');
    Route::post('/imports/{job}/cancel', [ImportApiController::class, 'cancel'])->name('imports.cancel');

    Route::post('/exports', [ExportApiController::class, 'store'])->name('exports.store');
    Route::get('/exports/{job}', [ExportApiController::class, 'show'])->name('exports.show');
    Route::get('/exports/{job}/download', [ExportApiController::class, 'download'])->name('exports.download');
});
