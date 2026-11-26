<?php

declare(strict_types=1);

namespace App\Modules\AttendanceDevice\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Attendance terminals (AnnotechERP 03/01): the ZKTeco ADMS endpoints sit at
 * the root of the server, /iclock/*, because a terminal only takes a server
 * address and port — the path is fixed in its firmware. No session, cookie
 * or CSRF: a terminal is known by its serial number (and, when set, its
 * address). Limited per address so one misbehaving terminal cannot flood it.
 */
class AttendanceDeviceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('adms', fn (Request $request) => Limit::perMinute(600)->by('adms|'.$request->ip()));

        Route::middleware('throttle:adms')->prefix('iclock')->name('adms.')->group(__DIR__.'/../Routes/device.php');
    }
}
