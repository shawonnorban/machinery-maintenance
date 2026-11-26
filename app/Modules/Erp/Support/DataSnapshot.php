<?php

declare(strict_types=1);

namespace App\Modules\Erp\Support;

use Closure;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * A copy of every business table — companies, factories, buyers, users,
 * employees, brands, seasons, departments, styles, orders and the rest — kept
 * as files under database/snapshot, so another server (production) can be
 * filled with exactly this data by Database\Seeders\SnapshotSeeder (user
 * 2026-11-26).
 *
 * Export: `php artisan erp:snapshot-export` writes manifest.json (tables in
 * foreign-key order, row counts) and one gzipped JSON-lines file per table.
 *
 * Import: empties every table named in the manifest (TRUNCATE … CASCADE, so
 * rows of excluded tables that point at them — tokens, sessions, logs — go
 * too) and inserts the snapshot rows with their original ids, then moves the
 * id sequences past the highest id. Columns the target no longer has are
 * dropped; tables the target does not have are skipped with a warning.
 *
 * PostgreSQL only — that is what dev and production both run.
 */
final class DataSnapshot
{
    public const DIR = 'database/snapshot';

    /**
     * Framework plumbing and dev-only history that must not travel: queue and
     * cache rows, login tokens and sessions, scheduler run log, push endpoints
     * tied to this machine's VAPID keys. audit_logs is left out unless asked —
     * here it is mostly the record of test clicks.
     */
    public const EXCLUDED = [
        'migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
        'password_reset_tokens', 'personal_access_tokens', 'idempotency_keys', 'login_attempts',
        'scheduled_job_runs', 'notifications', 'notification_deliveries',
        'chat_presence', 'chat_push_subscriptions',
    ];

    public const AUDIT_TABLES = ['audit_logs'];

    /** Columns encrypted with APP_KEY: kept only when this server can read them. */
    private const ENCRYPTED = ['ai_services' => ['api_key']];

    /**
     * @param  Closure(string):void  $say
     * @return array{tables:int, rows:int}
     */
    public static function export(bool $withAudit, Closure $say): array
    {
        $dir = base_path(self::DIR);
        $tablesDir = $dir.'/tables';
        if (! is_dir($tablesDir) && ! mkdir($tablesDir, 0775, true) && ! is_dir($tablesDir)) {
            throw new RuntimeException("Cannot create {$tablesDir}");
        }
        foreach (glob($tablesDir.'/*.jsonl.gz') ?: [] as $old) {
            unlink($old);
        }

        $excluded = $withAudit ? self::EXCLUDED : [...self::EXCLUDED, ...self::AUDIT_TABLES];
        $tables = array_values(array_filter(
            self::orderedTables(),
            fn (string $t) => ! in_array($t, $excluded, true) && ! str_starts_with($t, 'telescope_') && ! str_starts_with($t, 'pulse_'),
        ));

        $manifest = [];
        $total = 0;
        foreach ($tables as $table) {
            $rows = self::exportTable($table, $tablesDir);
            $manifest[] = ['table' => $table, 'rows' => $rows];
            $total += $rows;
            if ($rows > 0) {
                $say(sprintf('%-45s %7d', $table, $rows));
            }
        }

        file_put_contents($dir.'/manifest.json', json_encode([
            'exported_at' => now()->toIso8601String(),
            'excluded' => $excluded,
            'tables' => $manifest,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        return ['tables' => count($tables), 'rows' => $total];
    }

    /**
     * @param  Closure(string):void  $say
     * @param  Closure(string):void  $warn
     * @return array{tables:int, rows:int}
     */
    public static function import(Closure $say, Closure $warn): array
    {
        $dir = base_path(self::DIR);
        $manifestPath = $dir.'/manifest.json';
        if (! is_file($manifestPath)) {
            throw new RuntimeException('No snapshot: '.self::DIR.'/manifest.json is missing. Run erp:snapshot-export on the source first.');
        }
        $manifest = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        $say('Snapshot taken '.$manifest['exported_at']);

        $columns = self::targetColumns();
        $entries = [];
        foreach ($manifest['tables'] as $entry) {
            if (! isset($columns[$entry['table']])) {
                $warn("{$entry['table']}: not in this database — skipped.");

                continue;
            }
            $entries[] = $entry;
        }

        $total = 0;
        DB::transaction(function () use ($entries, $columns, $dir, $say, $warn, &$total) {
            // Load in any order without tripping foreign keys or triggers;
            // the snapshot is a consistent copy, so the keys hold once done.
            try {
                DB::statement('SET LOCAL session_replication_role = replica');
            } catch (Throwable) {
                $warn('Could not switch off foreign-key checks (needs a superuser); loading in key order instead.');
            }

            $names = implode(', ', array_map(fn ($e) => self::quote($e['table']), $entries));
            DB::statement("TRUNCATE {$names} RESTART IDENTITY CASCADE");

            foreach ($entries as $entry) {
                if ($entry['rows'] === 0) {
                    continue;
                }
                $loaded = self::importTable($entry['table'], $columns[$entry['table']], $dir.'/tables', $warn);
                $total += $loaded;
                $say(sprintf('%-45s %7d', $entry['table'], $loaded));
            }

            self::resetSequences(array_column($entries, 'table'));
        });

        return ['tables' => count($entries), 'rows' => $total];
    }

    private static function exportTable(string $table, string $dir): int
    {
        $query = DB::table($table);
        foreach (self::primaryKey($table) as $key) {
            $query->orderBy($key);
        }

        $count = 0;
        $gz = null;
        foreach ($query->cursor() as $row) {
            $gz ??= gzopen($dir.'/'.$table.'.jsonl.gz', 'wb9');
            gzwrite($gz, json_encode((array) $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR)."\n");
            $count++;
        }
        if ($gz !== null) {
            gzclose($gz);
        }

        return $count;
    }

    /**
     * @param  list<string>  $targetColumns
     * @param  Closure(string):void  $warn
     */
    private static function importTable(string $table, array $targetColumns, string $dir, Closure $warn): int
    {
        $path = $dir.'/'.$table.'.jsonl.gz';
        if (! is_file($path)) {
            throw new RuntimeException("Snapshot file missing: {$path}");
        }

        $keep = null;
        $chunk = [];
        $chunkSize = 500;
        $count = 0;
        $encrypted = self::ENCRYPTED[$table] ?? [];
        $unreadable = 0;

        $gz = gzopen($path, 'rb');
        while (($line = gzgets($gz)) !== false) {
            if (trim($line) === '') {
                continue;
            }
            $row = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            if ($keep === null) {
                $keep = array_flip(array_intersect(array_keys($row), $targetColumns));
                $dropped = array_diff(array_keys($row), $targetColumns);
                if ($dropped !== []) {
                    $warn("{$table}: column(s) ".implode(', ', $dropped).' not in this database — left out.');
                }
                // PostgreSQL takes at most 65535 bound values per statement.
                $chunkSize = max(1, min(500, intdiv(60000, max(1, count($keep)))));
            }
            $row = array_intersect_key($row, $keep);
            foreach ($encrypted as $column) {
                if (isset($row[$column]) && ! self::readable((string) $row[$column])) {
                    $row[$column] = null;
                    $unreadable++;
                }
            }
            $chunk[] = $row;
            if (count($chunk) >= $chunkSize) {
                DB::table($table)->insert($chunk);
                $count += count($chunk);
                $chunk = [];
            }
        }
        gzclose($gz);

        if ($chunk !== []) {
            DB::table($table)->insert($chunk);
            $count += count($chunk);
        }
        if ($unreadable > 0) {
            $warn("{$table}: {$unreadable} encrypted value(s) were made with another APP_KEY — cleared; enter them again.");
        }

        return $count;
    }

    private static function readable(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /** @param list<string> $tables */
    private static function resetSequences(array $tables): void
    {
        $serials = DB::select(
            "select table_name, column_name from information_schema.columns
             where table_schema = current_schema() and (column_default like 'nextval%' or is_identity = 'YES')"
        );
        foreach ($serials as $s) {
            if (! in_array($s->table_name, $tables, true)) {
                continue;
            }
            $t = self::quote($s->table_name);
            $c = self::quote($s->column_name);
            DB::select(
                "select setval(pg_get_serial_sequence(?, ?), coalesce((select max({$c}) from {$t}), 1), (select max({$c}) from {$t}) is not null)",
                [$s->table_name, $s->column_name],
            );
        }
    }

    /**
     * Every table, parents before the tables that reference them.
     *
     * @return list<string>
     */
    private static function orderedTables(): array
    {
        $tables = array_map(fn ($r) => $r->relname, DB::select(
            "select c.relname from pg_class c join pg_namespace n on n.oid = c.relnamespace
             where n.nspname = current_schema() and c.relkind in ('r', 'p') order by c.relname"
        ));
        $parents = array_fill_keys($tables, []);
        foreach (DB::select(
            "select ch.relname as child, pa.relname as parent from pg_constraint k
             join pg_class ch on ch.oid = k.conrelid join pg_class pa on pa.oid = k.confrelid
             join pg_namespace n on n.oid = k.connamespace
             where k.contype = 'f' and n.nspname = current_schema()"
        ) as $fk) {
            if ($fk->child !== $fk->parent && isset($parents[$fk->child])) {
                $parents[$fk->child][$fk->parent] = true;
            }
        }

        $ordered = [];
        $done = [];
        while (count($ordered) < count($tables)) {
            $progress = false;
            foreach ($tables as $t) {
                if (isset($done[$t])) {
                    continue;
                }
                if (array_diff_key($parents[$t], $done) === []) {
                    $ordered[] = $t;
                    $done[$t] = true;
                    $progress = true;
                }
            }
            if (! $progress) {
                // A reference cycle: append the rest; the import switches
                // foreign-key checks off, so the order is only a fallback.
                foreach ($tables as $t) {
                    if (! isset($done[$t])) {
                        $ordered[] = $t;
                        $done[$t] = true;
                    }
                }
            }
        }

        return $ordered;
    }

    /** @return list<string> */
    private static function primaryKey(string $table): array
    {
        return array_map(fn ($r) => $r->attname, DB::select(
            'select a.attname from pg_index i
             join pg_attribute a on a.attrelid = i.indrelid and a.attnum = any(i.indkey)
             where i.indrelid = ?::regclass and i.indisprimary
             order by array_position(i.indkey::int2[], a.attnum)',
            [self::quote($table)],
        ));
    }

    /** @return array<string, list<string>> */
    private static function targetColumns(): array
    {
        $out = [];
        foreach (DB::select(
            'select table_name, column_name from information_schema.columns
             where table_schema = current_schema() order by table_name, ordinal_position'
        ) as $c) {
            $out[$c->table_name][] = $c->column_name;
        }

        return $out;
    }

    private static function quote(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
