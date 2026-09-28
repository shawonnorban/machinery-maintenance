<?php

declare(strict_types=1);

use App\Modules\Reporting\Http\Controllers\Web\ImportController;
use App\Modules\Reporting\Http\Controllers\Web\ReportController;
use App\Modules\Reporting\Http\Controllers\Web\ReportJobController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

    // Before the wildcard, or "jobs" would be read as a report key.
    Route::get('/reports/jobs', [ReportJobController::class, 'index'])->name('reports.jobs');
    Route::get('/reports/jobs/{job}/download', [ReportJobController::class, 'download'])
        ->name('reports.jobs.download');

    Route::get('/reports/{key}', [ReportController::class, 'show'])->name('reports.show');
    Route::post('/reports/{key}/export', [ReportController::class, 'export'])->name('reports.export');

    /*
     * Import: upload, validate, review, confirm (SRS 33). The job routes come
     * before the type wildcard for the same reason as above.
     */
    Route::get('/imports', [ImportController::class, 'index'])->name('imports.index');
    Route::get('/imports/jobs/{job}', [ImportController::class, 'review'])->name('imports.review');
    Route::get('/imports/jobs/{job}/errors', [ImportController::class, 'errors'])->name('imports.errors');
    Route::post('/imports/jobs/{job}/confirm', [ImportController::class, 'confirm'])->name('imports.confirm');
    Route::post('/imports/jobs/{job}/cancel', [ImportController::class, 'cancel'])->name('imports.cancel');

    Route::get('/exports/{job}/download', [ImportController::class, 'download'])->name('imports.download');

    Route::get('/imports/{type}', [ImportController::class, 'show'])->name('imports.show');
    Route::post('/imports/{type}/export', [ImportController::class, 'export'])->name('imports.export');
    Route::get('/imports/{type}/template', [ImportController::class, 'template'])->name('imports.template');
    Route::post('/imports/{type}', [ImportController::class, 'store'])->name('imports.store');
});
