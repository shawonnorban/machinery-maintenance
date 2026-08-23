<?php

declare(strict_types=1);

use App\Modules\Notification\Http\Controllers\Web\EscalationRuleController;
use App\Modules\Notification\Http\Controllers\Web\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::get('/notifications/preferences', [NotificationController::class, 'preferences'])
        ->name('notifications.preferences');
    Route::post('/notifications/preferences', [NotificationController::class, 'savePreferences'])
        ->name('notifications.preferences.save');

    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])
        ->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])
        ->name('notifications.read');
    // Distinct from reading: this is the act that stops an escalation.
    Route::post('/notifications/{notification}/acknowledge', [NotificationController::class, 'acknowledge'])
        ->name('notifications.acknowledge');
});

/*
 * Escalation rules (SRS 28): how long something may sit unanswered before it
 * goes past the person who ignored it. Configuration, so it sits under
 * settings with the rest of them.
 */
Route::middleware('auth')->prefix('settings')->name('settings.')->group(function (): void {
    Route::get('/escalations', [EscalationRuleController::class, 'index'])->name('escalations');
    Route::post('/escalations', [EscalationRuleController::class, 'store'])->name('escalations.store');
    Route::post('/escalations/{rule}/toggle', [EscalationRuleController::class, 'toggle'])->name('escalations.toggle');
    Route::delete('/escalations/{rule}', [EscalationRuleController::class, 'destroy'])->name('escalations.destroy');
});
