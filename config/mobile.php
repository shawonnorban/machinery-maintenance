<?php

declare(strict_types=1);

/*
 * The Android app (AnnotechERP 37, phase 6). The app reads these from
 * /api/v1/health: below `min_version` it stops, sends what is still on the
 * phone, and asks to be updated from `download_url`; `latest_version` only
 * tells that a newer one exists. Set in .env on each server.
 */
return [
    'min_version' => env('MOBILE_MIN_VERSION', '0.0.0'),
    'latest_version' => env('MOBILE_LATEST_VERSION'),
    'download_url' => env('MOBILE_DOWNLOAD_URL'),

    /*
     * Chat's live line on the phone (2026-11-27): the Reverb socket the web
     * uses, reached by the app itself. Only the public app key goes out —
     * never the secret. `host` empty = the same host the app talks to;
     * `path` for a socket behind a proxy path (e.g. nginx `/app`).
     */
    'reverb' => [
        'key' => env('REVERB_APP_KEY'),
        'host' => env('MOBILE_REVERB_HOST'),
        'port' => env('MOBILE_REVERB_PORT', '8090'),
        'scheme' => env('MOBILE_REVERB_SCHEME', 'http'),
    ],
];
