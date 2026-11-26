<?php

declare(strict_types=1);

use App\Modules\AttendanceDevice\Http\Controllers\AdmsController;
use Illuminate\Support\Facades\Route;

/*
 * The ZKTeco ADMS (push) endpoints (AnnotechERP 03/01), at /iclock/* — some
 * firmwares add ".aspx" to every path.
 */
foreach (['', '.aspx'] as $ext) {
    Route::match(['get', 'post'], "cdata{$ext}", [AdmsController::class, 'cdata'])->name("cdata{$ext}");
    Route::post("querydata{$ext}", [AdmsController::class, 'querydata'])->name("querydata{$ext}");
    Route::get("getrequest{$ext}", [AdmsController::class, 'getrequest'])->name("getrequest{$ext}");
    Route::post("devicecmd{$ext}", [AdmsController::class, 'devicecmd'])->name("devicecmd{$ext}");
    Route::get("ping{$ext}", [AdmsController::class, 'ping'])->name("ping{$ext}");
}
