<?php

declare(strict_types=1);

namespace App\Shared\JobNo;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The company's Job No (user, 2026-11-22): year + factory + serial, e.g.
 * 26NCL00001 — the year's last two digits (today's, so it turns over every
 * January), the order's factory code, a serial of at least five digits. The
 * entry dialog fills the year and factory itself and offers the next serial
 * of that year and factory; a pasted or imported number is kept as it is.
 *
 * Order Summary first; Pre-Budget, Dev Sample Status and Export Statement
 * take the same once it has been reviewed, so nothing here is Order Summary's own.
 */
final class JobNumber
{
    public const WIDTH = 5;

    /** 26NCL00001 → [26, NCL, 1]. */
    public const PATTERN = '/^(\d{2})([A-Z]{2,6})(\d{3,})$/';

    public static function year(string $today): string
    {
        return substr($today, 2, 2);
    }

    public static function prefix(string $today, string $factory): string
    {
        return self::year($today).strtoupper(trim($factory));
    }

    public static function make(string $prefix, int $serial): string
    {
        return $prefix.str_pad((string) $serial, self::WIDTH, '0', STR_PAD_LEFT);
    }

    /** @return array{year: string, factory: string, serial: int}|null */
    public static function parse(string $value): ?array
    {
        return preg_match(self::PATTERN, trim($value), $m) ? ['year' => $m[1], 'factory' => $m[2], 'serial' => (int) $m[3]] : null;
    }

    /**
     * Norban's older ways of writing it — NCL-24-00815, 24-NCL-00815 — in
     * today's form (24NCL00815); anything else comes back unchanged.
     */
    public static function modern(string $value): string
    {
        $v = trim($value);
        if (preg_match('/^([A-Z]{2,6})-(\d{2})-(\d{3,6})$/', $v, $m)) {
            return $m[2].$m[1].str_pad($m[3], self::WIDTH, '0', STR_PAD_LEFT);
        }
        if (preg_match('/^(\d{2})-([A-Z]{2,6})-(\d{3,6})$/', $v, $m)) {
            return $m[1].$m[2].str_pad($m[3], self::WIDTH, '0', STR_PAD_LEFT);
        }

        return $v;
    }

    /**
     * Where the company's Job No is written: one number per job, the same in
     * every module (a budget's requisition, the order, the sample, the export).
     * The next serial is one past the highest in any of them.
     */
    public const SOURCES = [
        'os_orders' => 'job_no',
        'pb_requisitions' => 'job_no',
        'ds_samples' => 'job_no',
        'es_statements' => 'job_no',
    ];

    /**
     * This year's serials of each factory in one table: every value of the
     * column (a jsonb array, or a lone string) in the live rows of one company.
     *
     * @return array<string, list<int>> factory code => serials, in order
     */
    public static function serials(string $table, string $companyId, string $column, string $year): array
    {
        $re = '^'.$year.'([A-Z]{2,6})(\d+)$';
        $rows = DB::select(
            "select distinct m[1] as f, m[2]::bigint as s
               from (select regexp_match(e, ?) as m
                       from {$table} t,
                            jsonb_array_elements_text(case jsonb_typeof(t.\"values\"->?)
                                when 'array' then t.\"values\"->?
                                when 'string' then jsonb_build_array(t.\"values\"->?)
                                else '[]'::jsonb end) e
                      where t.company_id = ? and t.deleted_at is null and e ~ ?) x
              order by 1, 2",
            [$re, $column, $column, $column, $companyId, $re],
        );
        $out = [];
        foreach ($rows as $r) {
            $out[$r->f][] = (int) $r->s;
        }

        return $out;
    }

    /**
     * What the entry dialog needs, per factory: this year's prefix, the next
     * serial (over every module) and the serials this table already has (a
     * number entered twice in one module is warned of; the same job in
     * another module is not).
     *
     * @param  list<string>  $factories
     * @return array<string, array{year: string, factory: string, prefix: string, width: int, next: int, taken: list<int>}>
     */
    public static function suggest(string $table, string $companyId, string $today, array $factories): array
    {
        $year = self::year($today);
        $own = self::serials($table, $companyId, self::SOURCES[$table] ?? 'job_no', $year);
        $high = [];
        foreach (self::SOURCES as $t => $column) {
            $all = $t === $table ? $own : self::serials($t, $companyId, $column, $year);
            foreach ($all as $f => $list) {
                $high[$f] = max($high[$f] ?? 0, max($list));
            }
        }
        $out = [];
        foreach ($factories as $f) {
            $f = strtoupper(trim($f));
            $out[$f] = ['year' => $year, 'factory' => $f, 'prefix' => $year.$f, 'width' => self::WIDTH, 'next' => ($high[$f] ?? 0) + 1, 'taken' => $own[$f] ?? []];
        }

        return $out;
    }

    /**
     * The Job No endpoint of a module: `?factory=NCL` gives that factory's
     * suggestion; no factory gives every factory of the company at once (the
     * sheet loads them as it opens, so the dialog shows at once).
     */
    public static function answer(string $table, string $companyId, string $today, ?string $factory): array
    {
        $codes = DB::table('factories')->where('company_id', $companyId)->orderBy('code')->pluck('code')->map(fn ($c) => strtoupper((string) $c))->all();
        $factory = strtoupper(trim((string) $factory));
        if ($factory === '') {
            return ['factories' => self::suggest($table, $companyId, $today, $codes)];
        }
        if (! in_array($factory, $codes, true)) {
            throw ValidationException::withMessages(['factory' => [__('ordersummary.v.job_factory')]]);
        }

        return self::suggest($table, $companyId, $today, [$factory])[$factory];
    }
}
