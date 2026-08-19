<?php

declare(strict_types=1);

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
});
