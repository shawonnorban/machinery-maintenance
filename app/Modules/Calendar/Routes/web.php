<?php

declare(strict_types=1);

use App\Modules\Calendar\Http\Controllers\Web\CalendarController;
use Illuminate\Support\Facades\Route;

/*
 * The working week (SRS 7).
 *
 * Availability divides downtime by scheduled operating time, and a maintenance
 * date that falls on a rest day is moved to the next working one. Both read
 * what these screens write.
 */
Route::middleware('auth')->prefix('settings')->name('settings.')->group(function (): void {
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar');

    // A calendar is superseded from a date rather than edited: last quarter's
    // availability was computed against last quarter's week.
    Route::post('/calendar', [CalendarController::class, 'storeCalendar'])->name('calendar.store');

    Route::post('/calendar/shifts', [CalendarController::class, 'storeShift'])->name('calendar.shifts.store');
    Route::patch('/calendar/shifts/{shift}', [CalendarController::class, 'updateShift'])->name('calendar.shifts.update');
    Route::delete('/calendar/shifts/{shift}', [CalendarController::class, 'destroyShift'])->name('calendar.shifts.destroy');

    Route::post('/calendar/holidays', [CalendarController::class, 'storeHoliday'])->name('calendar.holidays.store');
    Route::delete('/calendar/holidays/{holiday}', [CalendarController::class, 'destroyHoliday'])
        ->name('calendar.holidays.destroy');
});
