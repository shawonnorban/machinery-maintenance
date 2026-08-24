<?php

declare(strict_types=1);

/*
 * Uploaded files (SRS 37, API 19.1).
 *
 * Per-installation, not per-company: a company cannot be allowed to turn its
 * own virus scanning off, and the scanner binary is a property of the machine
 * the application runs on. Anything that genuinely varies per customer belongs
 * in `settings` instead (ADR-054).
 */

return [
    'scan' => [
        /*
         * Off by default, and that default is deliberate.
         *
         * On, with no scanner installed, every upload stays PENDING and refuses
         * to download — inconvenient, and correct: the alternative is a setting
         * that claims a check nobody performed. Off, every upload is recorded
         * as SKIPPED and stays usable, and the row says plainly that it was
         * never checked.
         */
        'enabled' => env('VIRUS_SCAN_ENABLED', false),

        /*
         * The command, as an argument list rather than a string. A string would
         * go through a shell, and a filename is attacker-influenced input.
         *
         * `clamdscan` talks to a resident daemon, so it does not reload the
         * signature database on every upload the way `clamscan` does.
         */
        'command' => ['clamdscan', '--no-summary', '--fdpass'],
    ],
];
