<?php

declare(strict_types=1);

/**
 * phpunit.xml's own <env name="DB_DATABASE" force="true"/> does not reach
 * Laravel's env() helper here: PHP populates $_SERVER from the real
 * container environment before PHPUnit's bootstrap ever runs, and
 * Laravel's dotenv-backed Env::get() consults $_SERVER ahead of the
 * $_ENV/putenv() values PHPUnit's <env> block actually sets — confirmed by
 * a throwaway diagnostic test 2026-09-08 (getenv() and $_ENV correctly
 * showed the overridden test database; $_SERVER and env() both still
 * showed the container's real dev database).
 *
 * That mismatch is not cosmetic: with the real dev database name winning,
 * RefreshDatabase's migrate:fresh ran against — and wiped — the live dev
 * database twice in one session, once even after the <env force="true">
 * fix was already in place. Setting $_SERVER directly, before Laravel's
 * autoloader or anything else loads, is the only override every code path
 * that reads it will agree on.
 */
/**
 * The same $_SERVER-wins-over-$_ENV problem documented above for
 * DB_DATABASE turns out to apply to every <env> line in phpunit.xml, not
 * just that one — confirmed 2026-09-09 by comparing `docker compose exec
 * backend printenv` against phpunit.xml's declared values: the container's
 * real .env sets QUEUE_CONNECTION=redis, CACHE_STORE=redis, SESSION_
 * DRIVER=database, APP_ENV=local, none of which phpunit.xml's <env> block
 * actually overrides in Docker. The queue leak is the one with a real,
 * visible failure mode: a job dispatched during a test (e.g.
 * WriteAuditEntry) lands on the *real* Redis queue instead of running
 * synchronously in-process, and the persistent `queue:work redis`
 * container then processes it later against whatever the database looks
 * like by then — after RefreshDatabase has already torn down the company
 * that job's payload references, logged as a foreign-key violation
 * ("Audit entry could not be written; the trail has a gap") that looks
 * like a random flake but is really this. Forced the same way as
 * DB_DATABASE: directly into $_SERVER before Laravel's autoloader loads.
 *
 * Deliberately NOT forced here: DB_CONNECTION/DB_HOST/DB_USERNAME/
 * DB_PASSWORD (must stay whatever the real environment actually runs —
 * mysql on native WAMP, pgsql in this Docker setup) and BROADCAST_
 * CONNECTION / REVERB_* / MAIL_MAILER (the channel-auth tests deliberately
 * exercise the real broadcaster already reachable in this environment;
 * forcing the fake "testing" values phpunit.xml declares for those would
 * break that on purpose-real check, not fix a leak).
 */
$forced = [
    'DB_DATABASE' => 'machinery_maintenance_test',
    'APP_ENV' => 'testing',
    'CACHE_STORE' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
];

foreach ($forced as $key => $value) {
    $_SERVER[$key] = $value;
    $_ENV[$key] = $value;
    putenv("{$key}={$value}");
}

require __DIR__.'/../vendor/autoload.php';
