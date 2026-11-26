<?php

declare(strict_types=1);

use App\Modules\AiImport\Http\Controllers\Api\AiImportApiController;
use Illuminate\Support\Facades\Route;

/*
 * AI File & PO Import (AnnotechERP 26). Its lists are ERP resources
 * (`/erp/ai-intake-files`, `ai-signatures`, `ai-extraction-rules`,
 * `ai-validations`, `ai-approval-steps`); this is what their pickers offer
 * and where a dropped file is uploaded.
 */
Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/ai-import/options', [AiImportApiController::class, 'options'])->name('ai-import.options');
    Route::post('/ai-import/intake-files', [AiImportApiController::class, 'intakeFile'])->name('ai-import.intake-files');
    Route::post('/ai-import/intake/{id}/read', [AiImportApiController::class, 'read'])->name('ai-import.intake.read');
});
