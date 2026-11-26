<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Support\Reports;

use Illuminate\Support\Collection;

/**
 * Knit Production → Reports (AnnotechERP 15/11): every report Brickland's
 * report screens list, read from the module's own tables at the moment
 * Preview is pressed. A report answers its columns ("key:type"), its rows,
 * and the stat tiles above the grid — each the sum of one of its columns.
 *
 * The reports live in traits by the part of the mill they read: production,
 * yarn, grey store, quality, collar/cuff, needles and oil, sub-contract.
 */
final class KnitReports
{
    use CcReports;
    use GreyReports;
    use NeedleOilReports;
    use ProductionReports;
    use QcReports;
    use ScReports;
    use YarnReports;

    public function __construct(private readonly ReportQuery $q) {}

    /** @return array{columns: list<array{key: string, type: string, group?: string}>, rows: list<array<string, mixed>>, stats: list<array{key: string, value: ?float, type: string}>} */
    public function run(string $report): array
    {
        $method = lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $report))));

        return $this->{$method}();
    }

    // ── Shaping ───────────────────────────────────────────────────────────

    /**
     * A report's answer: columns from "key:type" (text when untyped), rows in
     * column order, and stat tiles — `[stat => column]`, each the column's sum (or `[stat => [value, type]]`);
     * Rows (the count) always comes first.
     *
     * @param  list<string>  $spec
     * @param  iterable<array<string, mixed>>  $rows
     * @param  array<string, string>  $stats
     */
    private function table(array $spec, iterable $rows, array $stats = []): array
    {
        // "key:type@group" — a group heads the columns under it, as Brickland's two-row headers do.
        $columns = array_map(function (string $s): array {
            [$s, $group] = array_pad(explode('@', $s, 2), 2, null);
            [$key, $type] = array_pad(explode(':', $s, 2), 2, 'text');

            return ['key' => $key, 'type' => $type] + ($group ? ['group' => $group] : []);
        }, $spec);
        $types = array_column($columns, 'type', 'key');
        $rows = collect($rows)->values();
        $out = [['key' => 'rows', 'value' => (float) $rows->count(), 'type' => 'int']];
        foreach ($stats as $stat => $col) {
            // A tile that is not a column's sum ("Overall Loss %", "Avg Cost/Kg") comes worked out: [value, type].
            if (is_array($col)) {
                $out[] = ['key' => $stat, 'value' => $col[0] === null ? null : (float) $col[0], 'type' => $col[1] ?? 'number'];

                continue;
            }
            $type = $types[$col] ?? 'number';
            $sum = $rows->sum(fn ($r) => (float) ($r[$col] ?? 0));
            $out[] = ['key' => $stat, 'value' => round($sum, $type === 'money' ? 2 : 3), 'type' => in_array($type, ['money', 'int'], true) ? $type : 'number'];
        }

        return ['columns' => $columns, 'rows' => $rows->all(), 'stats' => $out];
    }

    /**
     * Rows grouped on `keys`: the first row's values for those keys, `entries`
     * (the count) and the sums of `sums`; `extra` adds what a group derives.
     *
     * @param  list<string>  $keys
     * @param  list<string>  $sums
     */
    private function group(Collection $rows, array $keys, array $sums, ?callable $extra = null): Collection
    {
        return $rows->groupBy(fn ($r) => implode('|', array_map(fn ($k) => (string) ($r[$k] ?? ''), $keys)))
            ->map(function (Collection $g) use ($keys, $sums, $extra): array {
                $first = $g->first();
                $row = array_intersect_key($first, array_flip($keys)) + ['entries' => $g->count()];
                foreach ($sums as $s) {
                    $row[$s] = round($g->sum(fn ($r) => (float) ($r[$s] ?? 0)), 3);
                }

                return $extra ? $extra($row, $g) : $row;
            })->values();
    }

    private static function pct(float $part, float $whole): ?float
    {
        return $whole > 0 ? round(100 * $part / $whole, 2) : null;
    }

    private static function r3(mixed $v): float
    {
        return round((float) $v, 3);
    }
}
