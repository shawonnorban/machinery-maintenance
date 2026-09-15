<?php

declare(strict_types=1);

use App\Modules\Tenancy\Http\Controllers\Web\PreferenceController;
use Illuminate\Support\Facades\Route;

/*
 * The dashboard and factory-administration *screens* this file used to
 * register here are gone (Phase D/F, docs/12-Stack-Migration-
 * Implementation-Plan.md) — both fully replaced by the Next.js app.
 *
 * These two survive on purpose: neither has a screen of its own, they're
 * plain session-preference writes a form posts to and gets redirected
 * back from, and the still-Blade Platform admin layout's own language
 * switcher (`Platform/Resources/views/layout.blade.php`) posts to
 * `app.locale` directly — decommissioning this file wholesale would have
 * taken that down with it for no reason, since it costs nothing to leave
 * two routes with no view live.
 */
Route::middleware('auth')->group(function (): void {
    Route::post('/factory-scope', [PreferenceController::class, 'factoryScope'])->name('factory-scope');
    Route::post('/locale', [PreferenceController::class, 'locale'])->name('locale');
});
