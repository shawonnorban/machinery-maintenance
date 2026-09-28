<?php

declare(strict_types=1);

namespace App\Shared\Support;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The handful of SQL fragments the query builder has no driver-agnostic
 * method for, kept in one place instead of copy-pasted per report.
 *
 * `DATE_FORMAT()` is MySQL/MariaDB syntax; Postgres has no equivalent
 * function name and uses `TO_CHAR()` with different format tokens. A
 * `selectRaw()` call bypasses the query builder's own dialect abstraction
 * entirely, so this is the one place that has to know which driver is live.
 */
class Sql
{
    /**
     * A `YYYY-MM` bucket for grouping rows by month, aliased to `$as`.
     */
    public static function monthBucket(string $column, string $as = 'ym'): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "TO_CHAR({$column}, 'YYYY-MM') as {$as}",
            default => "DATE_FORMAT({$column}, '%Y-%m') as {$as}",
        };
    }

    /**
     * A `YYYY-MM-DD` bucket for grouping rows by day, aliased to `$as`.
     */
    public static function dayBucket(string $column, string $as = 'day'): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "TO_CHAR({$column}, 'YYYY-MM-DD') as {$as}",
            default => "DATE_FORMAT({$column}, '%Y-%m-%d') as {$as}",
        };
    }

    /**
     * Sort a column by an explicit value ranking, e.g. priority
     * CRITICAL/HIGH/MEDIUM/LOW rather than alphabetical order.
     *
     * MySQL's `FIELD()` does this in one call but has no Postgres
     * equivalent; a simple `CASE` expression is ANSI SQL and works
     * unchanged on both. Ranks start at 1, matching FIELD()'s own
     * numbering, and anything not in `$values` ranks 0 — FIELD()'s
     * documented behaviour for a value it doesn't find — so a stray value
     * outside the expected set (these columns carry no DB-level check
     * constraint) sorts first exactly as it did before, not last.
     *
     * @param  list<string>  $values  ranked first to last
     */
    public static function orderByList(string $column, array $values): string
    {
        $cases = array_map(
            fn (int $rank, string $value): string => "WHEN '".str_replace("'", "''", $value)."' THEN {$rank}",
            range(1, count($values)),
            $values,
        );

        return "CASE {$column} ".implode(' ', $cases).' ELSE 0 END';
    }

    /**
     * Sort rows matching `$column = $value` last.
     *
     * `ORDER BY column = 'X'` relies on booleans sorting like 0/1, which
     * MySQL and Postgres both do ascending, but an explicit `CASE` says so
     * instead of relying on that to keep being true.
     */
    public static function sortMatchLast(string $column, string $value): string
    {
        return "CASE WHEN {$column} = '".str_replace("'", "''", $value)."' THEN 1 ELSE 0 END";
    }

    /**
     * Run $callback with foreign-key enforcement suspended across $tables.
     *
     * MySQL and SQLite have one connection-wide switch for this
     * (`Schema::disableForeignKeyConstraints()`). Postgres has no
     * equivalent: `SET CONSTRAINTS ALL DEFERRED` only affects constraints
     * declared DEFERRABLE, and none of this schema's foreign keys are — so
     * the Postgres path disables the triggers that enforce each key
     * instead, table by table. That needs table-owner privilege only, not
     * superuser, and no migration change.
     *
     * @param  iterable<string>  $tables  every table $callback writes to,
     *         so its triggers are the ones suspended
     */
    public static function withoutForeignKeyChecks(iterable $tables, Closure $callback): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            Schema::disableForeignKeyConstraints();

            try {
                $callback();
            } finally {
                Schema::enableForeignKeyConstraints();
            }

            return;
        }

        $tables = is_array($tables) ? $tables : iterator_to_array($tables);

        foreach ($tables as $table) {
            DB::statement("ALTER TABLE \"{$table}\" DISABLE TRIGGER ALL");
        }

        try {
            $callback();
        } finally {
            foreach ($tables as $table) {
                DB::statement("ALTER TABLE \"{$table}\" ENABLE TRIGGER ALL");
            }
        }
    }
}
