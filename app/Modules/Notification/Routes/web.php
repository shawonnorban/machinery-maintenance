<?php

declare(strict_types=1);

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
