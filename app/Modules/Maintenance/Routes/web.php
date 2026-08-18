<?php

declare(strict_types=1);

use App\Modules\Maintenance\Http\Controllers\Web\TemplateController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/maintenance/templates', [TemplateController::class, 'index'])
        ->name('maintenance.templates');
    Route::get('/maintenance/templates/{template}', [TemplateController::class, 'show'])
        ->name('maintenance.templates.show');
    Route::get('/maintenance/templates/{template}/versions/{version}', [TemplateController::class, 'version'])
        ->name('maintenance.templates.version');
});
