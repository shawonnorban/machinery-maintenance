<?php

declare(strict_types=1);

use App\Modules\Settings\Http\Controllers\Api\IntegrationHubApiController;
use App\Modules\Settings\Http\Controllers\Api\MasterDataApiController;
use App\Modules\Settings\Http\Controllers\Api\NumberingApiController;
use App\Modules\Settings\Http\Controllers\Api\SettingsApiController;
use App\Modules\Settings\Http\Controllers\Api\SystemMaintenanceApiController;
use Illuminate\Support\Facades\Route;

/*
 * Reference data: asset types, categories, manufacturers, models,
 * maintenance types, failure codes, root causes, warehouses, stores, bins,
 * and the rest of MasterDataRegistry (API 5.2, Gap Analysis 3.4 #34).
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/settings', [SettingsApiController::class, 'index'])->name('settings.index');
    Route::get('/settings/definitions', [SettingsApiController::class, 'definitions'])->name('settings.definitions');
    Route::post('/settings/company/logo', [SettingsApiController::class, 'updateLogo'])->name('settings.company.logo.update');
    Route::delete('/settings/company/logo', [SettingsApiController::class, 'destroyLogo'])->name('settings.company.logo.destroy');
    // Settings → System & Maintenance (AnnotechERP 03/04). Before /settings/{key},
    // which would otherwise take PUT /settings/retention for a setting key.
    Route::get('/settings/notification-defaults', [SystemMaintenanceApiController::class, 'notificationDefaults'])->name('settings.notification-defaults.index');
    Route::put('/settings/notification-defaults', [SystemMaintenanceApiController::class, 'saveNotificationDefaults'])->name('settings.notification-defaults.save');
    Route::delete('/settings/notification-defaults/{event}', [SystemMaintenanceApiController::class, 'resetNotificationDefault'])->name('settings.notification-defaults.reset');
    Route::get('/settings/scheduled-jobs', [SystemMaintenanceApiController::class, 'scheduledJobs'])->name('settings.scheduled-jobs.index');
    Route::get('/settings/scheduled-jobs/runs', [SystemMaintenanceApiController::class, 'jobRuns'])->name('settings.scheduled-jobs.runs');
    Route::get('/settings/backups', [SystemMaintenanceApiController::class, 'backups'])->name('settings.backups.index');
    Route::post('/settings/backups', [SystemMaintenanceApiController::class, 'createBackup'])->name('settings.backups.store');
    Route::get('/settings/backups/{backup}/download', [SystemMaintenanceApiController::class, 'downloadBackup'])->name('settings.backups.download');
    Route::get('/settings/restores', [SystemMaintenanceApiController::class, 'restores'])->name('settings.restores.index');
    Route::post('/settings/restores', [SystemMaintenanceApiController::class, 'uploadRestore'])->name('settings.restores.store');
    Route::post('/settings/restores/{restore}/apply', [SystemMaintenanceApiController::class, 'applyRestore'])->name('settings.restores.apply');
    Route::get('/settings/system-health', [SystemMaintenanceApiController::class, 'systemHealth'])->name('settings.system-health');
    Route::get('/settings/retention', [SystemMaintenanceApiController::class, 'retention'])->name('settings.retention.index');
    Route::put('/settings/retention', [SystemMaintenanceApiController::class, 'saveRetention'])->name('settings.retention.save');
    Route::post('/settings/retention/purge', [SystemMaintenanceApiController::class, 'purge'])->name('settings.retention.purge');

    Route::put('/settings/{key}', [SettingsApiController::class, 'update'])->name('settings.update');
    Route::delete('/settings/{key}', [SettingsApiController::class, 'destroy'])->name('settings.destroy');

    // Settings → Integration Hub (AnnotechERP 03/02).
    Route::get('/settings/integration/endpoints', [IntegrationHubApiController::class, 'endpoints'])->name('settings.integration.endpoints');
    Route::get('/settings/integration/activity', [IntegrationHubApiController::class, 'activity'])->name('settings.integration.activity');
    Route::post('/settings/sms-gateways/{gateway}/test', [IntegrationHubApiController::class, 'testSms'])->name('settings.sms.test');
    Route::post('/settings/bank-integrations/{bank}/test', [IntegrationHubApiController::class, 'testBank'])->name('settings.bank.test');

    Route::get('/numbering', [NumberingApiController::class, 'index'])->name('numbering.index');
    Route::patch('/numbering/{documentType}', [NumberingApiController::class, 'update'])->name('numbering.update');
    Route::delete('/numbering/{documentType}', [NumberingApiController::class, 'reset'])->name('numbering.reset');

    Route::get('/master-data', [MasterDataApiController::class, 'index'])->name('master-data.index');
    Route::get('/master-data/{type}', [MasterDataApiController::class, 'show'])->name('master-data.show');
    Route::patch('/master-data/{type}/{row}', [MasterDataApiController::class, 'update'])
        ->name('master-data.update');
    Route::patch('/master-data/{type}/{row}/active', [MasterDataApiController::class, 'setActive'])
        ->name('master-data.set-active');
    Route::delete('/master-data/{type}/{row}', [MasterDataApiController::class, 'destroy'])
        ->name('master-data.destroy');
});

Route::middleware(['api.auth', 'throttle:api', 'idempotent'])->group(function (): void {
    Route::post('/master-data/{type}', [MasterDataApiController::class, 'store'])->name('master-data.store');
});
