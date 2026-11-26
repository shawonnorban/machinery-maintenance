<?php

declare(strict_types=1);

use App\Modules\AttendanceDevice\Http\Controllers\Api\BiometricApiController;
use App\Modules\AttendanceDevice\Http\Controllers\Api\DeviceApiController;
use Illuminate\Support\Facades\Route;

/*
 * Settings → Device Integration (AnnotechERP 03/01) and HR & Payroll →
 * Biometric / Fingerprint (24/03). A device is addressed by its code. The
 * terminals' own calls are at /iclock/* (Routes/device.php).
 */
Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/settings/devices/options', [DeviceApiController::class, 'options'])->name('settings.devices.options');
    Route::get('/settings/devices', [DeviceApiController::class, 'index'])->name('settings.devices.index');
    Route::post('/settings/devices', [DeviceApiController::class, 'store'])->name('settings.devices.store');
    Route::get('/settings/devices/{device}', [DeviceApiController::class, 'show'])->name('settings.devices.show');
    Route::put('/settings/devices/{device}', [DeviceApiController::class, 'update'])->name('settings.devices.update');
    Route::delete('/settings/devices/{device}', [DeviceApiController::class, 'destroy'])->name('settings.devices.delete');
    Route::post('/settings/devices/{device}/action', [DeviceApiController::class, 'act'])->name('settings.devices.action');
    Route::get('/settings/device-punches', [DeviceApiController::class, 'punches'])->name('settings.device-punches.index');
    Route::post('/settings/device-punches/post-pending', [DeviceApiController::class, 'postPending'])->name('settings.device-punches.post');
    Route::get('/settings/device-users', [DeviceApiController::class, 'users'])->name('settings.device-users.index');
    Route::get('/settings/device-commands', [DeviceApiController::class, 'commands'])->name('settings.device-commands.index');
    Route::post('/settings/device-commands', [DeviceApiController::class, 'sendCommand'])->name('settings.device-commands.store');
    Route::post('/settings/device-commands/{id}/cancel', [DeviceApiController::class, 'cancelCommand'])->name('settings.device-commands.cancel');

    Route::get('/hr-payroll/biometric/overview', [BiometricApiController::class, 'overview'])->name('hr-payroll.biometric.overview');
    Route::get('/hr-payroll/biometric/employees', [BiometricApiController::class, 'employees'])->name('hr-payroll.biometric.employees');
    Route::get('/hr-payroll/biometric/unmapped', [BiometricApiController::class, 'unmapped'])->name('hr-payroll.biometric.unmapped');
    Route::post('/hr-payroll/biometric/enrol', [BiometricApiController::class, 'enrol'])->name('hr-payroll.biometric.enrol');
    Route::post('/hr-payroll/biometric/send', [BiometricApiController::class, 'send'])->name('hr-payroll.biometric.send');
    Route::post('/hr-payroll/biometric/collect', [BiometricApiController::class, 'collect'])->name('hr-payroll.biometric.collect');
    Route::post('/hr-payroll/biometric/delete', [BiometricApiController::class, 'delete'])->name('hr-payroll.biometric.delete');
    Route::post('/hr-payroll/biometric/map', [BiometricApiController::class, 'mapOne'])->name('hr-payroll.biometric.map');
    Route::post('/hr-payroll/biometric/unmap', [BiometricApiController::class, 'unmap'])->name('hr-payroll.biometric.unmap');
    Route::post('/hr-payroll/biometric/automap', [BiometricApiController::class, 'automap'])->name('hr-payroll.biometric.automap');
});
