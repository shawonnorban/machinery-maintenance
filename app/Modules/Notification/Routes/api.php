<?php

declare(strict_types=1);

use App\Modules\Notification\Http\Controllers\Api\EscalationRuleApiController;
use App\Modules\Notification\Http\Controllers\Api\NotificationApiController;
use Illuminate\Support\Facades\Route;

/*
 * A person's own notifications (API 19).
 */

Route::middleware(['api.auth', 'throttle:api'])->group(function (): void {
    Route::get('/notifications', [NotificationApiController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationApiController::class, 'markRead'])
        ->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationApiController::class, 'markAllRead'])
        ->name('notifications.read-all');
    Route::post('/notifications/{notification}/acknowledge', [NotificationApiController::class, 'acknowledge'])
        ->name('notifications.acknowledge');

    Route::get('/notification-preferences', [NotificationApiController::class, 'preferences'])
        ->name('notification-preferences.show');
    Route::patch('/notification-preferences', [NotificationApiController::class, 'savePreferences'])
        ->name('notification-preferences.update');

    Route::get('/escalation-rules', [EscalationRuleApiController::class, 'index'])->name('escalation-rules.index');
    Route::post('/escalation-rules', [EscalationRuleApiController::class, 'store'])->name('escalation-rules.store');
    Route::post('/escalation-rules/{rule}/toggle', [EscalationRuleApiController::class, 'toggle'])
        ->name('escalation-rules.toggle');
    Route::delete('/escalation-rules/{rule}', [EscalationRuleApiController::class, 'destroy'])
        ->name('escalation-rules.destroy');
});
