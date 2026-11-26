<?php

declare(strict_types=1);

namespace App\Modules\DevSample\Support;

use Illuminate\Support\Collection;

/**
 * Dev Sample Status → Pivot Report (AnnotechERP 31/01), Norban's "Running
 * Development Sample Summary":
 *
 *  - summary: Buyer × Order Confirmation counts, with row, column and grand
 *    totals; a blank or unknown confirmation is its own column, "(Blank)" last;
 *  - detail: Buyer × Sample Status (Submitted, Submission Pending, Cancelled,
 *    Self Drop, Grand Total) under the chosen levels — Sample Type, Job No,
 *    Styling, Item, Color, Fabrication. A sample with several items (or
 *    colours) gives each its own row at that level, but every total above
 *    it counts the sample once.
 *
 * Rows come in as presented samples (buyer name, codes, lists).
 */
final class DevSampleReport
{
    public const BLANK = '(Blank)';

    public const LEVELS = ['sample_type', 'job_no', 'styling', 'item', 'color', 'fabrication'];

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{columns: list<string>, rows: list<array{buyer: string, cells: array<string, int>, total: int}>, totals: array<string, int>, grand: int}
     */
    public static function summary(Collection $rows): array
    {
        $columns = DevSampleOptions::ORDER_CONFIRMATIONS;
        $matrix = [];
        foreach ($rows as $r) {
            $buyer = self::text($r['buyer']);
            $col = self::text($r['order_confirmation']);
            if ($col !== self::BLANK && ! in_array($col, $columns, true)) {
                $columns[] = $col;
            }
            $matrix[$buyer][$col] = ($matrix[$buyer][$col] ?? 0) + 1;
        }
        if (collect($matrix)->contains(fn ($cells) => isset($cells[self::BLANK]))) {
            $columns[] = self::BLANK;
        }
        ksort($matrix, SORT_NATURAL | SORT_FLAG_CASE);
        $totals = array_fill_keys($columns, 0);
        $out = [];
        foreach ($matrix as $buyer => $cells) {
            $line = [];
            foreach ($columns as $c) {
                $line[$c] = $cells[$c] ?? 0;
                $totals[$c] += $line[$c];
            }
            $out[] = ['buyer' => (string) $buyer, 'cells' => $line, 'total' => array_sum($line)];
        }

        return ['columns' => $columns, 'rows' => $out, 'totals' => $totals, 'grand' => array_sum($totals)];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  list<string>  $levels
     * @return array{columns: list<string>, tree: list<array<string, mixed>>, totals: array<string, int>}
     */
    public static function detail(Collection $rows, array $levels): array
    {
        $levels = array_values(array_intersect(self::LEVELS, $levels));
        $columns = DevSampleOptions::REPORT_SAMPLE_STATUSES;
        $tree = [];
        $grand = self::blankCounts($columns);
        foreach ($rows as $r) {
            $status = self::text($r['sample_status']);
            $path = [['buyer', [self::text($r['buyer'])]]];
            foreach ($levels as $level) {
                $path[] = [$level, self::values($r, $level)];
            }
            self::add($tree, $path, $status, $columns);
            self::bump($grand, $status, $columns);
        }

        return ['columns' => $columns, 'tree' => self::flatten($tree), 'totals' => $grand];
    }

    /**
     * Count the sample once on every node of its path; at a level with several
     * values each value gets the count, while the totals above stay at one.
     *
     * @param  list<array{0: string, 1: list<string>}>  $path
     */
    private static function add(array &$nodes, array $path, string $status, array $columns): void
    {
        [$level, $values] = array_shift($path);
        foreach ($values as $value) {
            $nodes[$value] ??= ['level' => $level, 'label' => $value, 'counts' => self::blankCounts($columns), 'children' => []];
            self::bump($nodes[$value]['counts'], $status, $columns);
            if ($path !== []) {
                self::add($nodes[$value]['children'], $path, $status, $columns);
            }
        }
    }

    /** @return array<string, int> */
    private static function blankCounts(array $columns): array
    {
        return array_fill_keys($columns, 0) + ['total' => 0];
    }

    private static function bump(array &$counts, string $status, array $columns): void
    {
        if (in_array($status, $columns, true)) {
            $counts[$status]++;
        }
        $counts['total']++;
    }

    /** @return list<array<string, mixed>> */
    private static function flatten(array $nodes): array
    {
        uksort($nodes, fn ($a, $b) => strnatcasecmp((string) $a, (string) $b));

        return array_values(array_map(fn (array $n): array => [...$n, 'label' => (string) $n['label'], 'children' => self::flatten($n['children'])], $nodes));
    }

    /** @return list<string> */
    private static function values(array $r, string $level): array
    {
        $raw = match ($level) {
            'item' => $r['items'] ?? [],
            'color' => array_column($r['colours'] ?? [], 'name'),
            default => [$r[$level] ?? null],
        };
        $values = array_values(array_unique(array_map(fn ($v) => self::text($v), $raw)));

        return $values === [] ? [self::BLANK] : $values;
    }

    private static function text(mixed $v): string
    {
        $v = trim((string) $v);

        return $v === '' ? self::BLANK : $v;
    }
}
