<?php

declare(strict_types=1);

use App\Modules\Erp\Http\Controllers\Api\ResourceApiController;
use Illuminate\Support\Facades\Route;

/*
 * The screen kit's resources (AnnotechERP): every Brickland "Records + New X"
 * screen built on a ResourceDefinition.
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/erp/{resource}', [ResourceApiController::class, 'index'])->name('erp.resources.index');
    Route::get('/erp/{resource}/{id}', [ResourceApiController::class, 'show'])->name('erp.resources.show');
    Route::patch('/erp/{resource}/{id}', [ResourceApiController::class, 'update'])->name('erp.resources.update');
    Route::delete('/erp/{resource}/{id}', [ResourceApiController::class, 'destroy'])->name('erp.resources.destroy');
});

Route::middleware(['api.auth', 'throttle:api', 'idempotent'])->group(function (): void {
    Route::post('/erp/{resource}', [ResourceApiController::class, 'store'])->name('erp.resources.store');
});
