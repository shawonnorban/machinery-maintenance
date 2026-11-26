<?php

declare(strict_types=1);

use App\Modules\Company\Http\Controllers\Api\CompanyDashboardApiController;
use App\Modules\Company\Http\Controllers\Api\CompanyDocumentApiController;
use App\Modules\Company\Http\Controllers\Api\CompanyProfileApiController;
use App\Modules\Company\Http\Controllers\Api\MachineRegisterApiController;
use Illuminate\Support\Facades\Route;

/*
 * Company Management (AnnotechERP 01) — the parts the master-data screens do
 * not cover: the company's own profile, its licences and certificates, and
 * the Brickland view of machinery maintenance's machine register.
 */
Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/company/dashboard', [CompanyDashboardApiController::class, 'show'])->name('company.dashboard');
    Route::get('/company/profile', [CompanyProfileApiController::class, 'show'])->name('company.profile.show');
    Route::put('/company/profile', [CompanyProfileApiController::class, 'update'])->name('company.profile.update');

    Route::get('/company/documents', [CompanyDocumentApiController::class, 'index'])->name('company.documents.index');
    Route::post('/company/documents', [CompanyDocumentApiController::class, 'store'])->name('company.documents.store');
    Route::patch('/company/documents/{document}', [CompanyDocumentApiController::class, 'update'])->name('company.documents.update');
    Route::delete('/company/documents/{document}', [CompanyDocumentApiController::class, 'destroy'])->name('company.documents.destroy');
    Route::get('/company/documents/{document}/files', [CompanyDocumentApiController::class, 'files'])->name('company.documents.files');
    Route::post('/company/documents/{document}/files', [CompanyDocumentApiController::class, 'attach'])->name('company.documents.attach');

    Route::get('/company/machines', [MachineRegisterApiController::class, 'index'])->name('company.machines.index');
    Route::patch('/company/machines/{asset}', [MachineRegisterApiController::class, 'update'])->name('company.machines.update');
});
