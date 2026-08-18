<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
 * Module API routes are auto-loaded by ModuleServiceProvider from
 * app/Modules/{Module}/Routes/api.php under the /api/v1 prefix.
 *
 * This file carries only cross-cutting endpoints (API 19.3).
 */

Route::get('/version', fn () => response()->json([
    'success' => true,
    'data' => [
        'api_version' => 'v1',
        'application' => config('app.name'),
    ],
]));
