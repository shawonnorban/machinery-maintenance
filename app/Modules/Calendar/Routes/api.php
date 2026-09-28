<?php

declare(strict_types=1);

use App\Modules\Calendar\Http\Controllers\Api\CalendarApiController;
use Illuminate\Support\Facades\Route;

/*
 * The working week (API 5.1).
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/factories/{factory}/calendar', [CalendarApiController::class, 'show'])->name('calendar.show');
    Route::put('/factories/{factory}/calendar', [CalendarApiController::class, 'update'])->name('calendar.update');

    Route::get('/factories/{factory}/shifts', [CalendarApiController::class, 'shifts'])->name('shifts.index');
    Route::post('/factories/{factory}/shifts', [CalendarApiController::class, 'storeShift'])->name('shifts.store');
    Route::patch('/shifts/{shift}', [CalendarApiController::class, 'updateShift'])->name('shifts.update');
    Route::delete('/shifts/{shift}', [CalendarApiController::class, 'destroyShift'])->name('shifts.destroy');

    Route::get('/factories/{factory}/holidays', [CalendarApiController::class, 'holidays'])->name('holidays.index');
    Route::post('/factories/{factory}/holidays', [CalendarApiController::class, 'storeHoliday'])->name('holidays.store');
    Route::delete('/holidays/{holiday}', [CalendarApiController::class, 'destroyHoliday'])->name('holidays.destroy');

    Route::get('/factories/{factory}/working-time', [CalendarApiController::class, 'workingTime'])
        ->name('working-time');
});
