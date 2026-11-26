<?php

declare(strict_types=1);

namespace App\Shared\Formula;

/**
 * A sheet's formula columns taken together: each read once, put in the order
 * they depend on one another (a loop is refused), and marked volatile when
 * they call TODAY() or read a column that does — those are worked out when a
 * row is read, never stored. `apply()` fills one row's formula columns, each
 * value shaped to its column (a date, a number, text). Numbers are not cut
 * to the column's places — like Excel, the places are how it shows.
 *
 * Columns: key => ['formula' => stored text or null, 'type' => …, 'decimals' => int].
 */
final class FormulaSet
{
    public const DATE_TYPES = ['date'];

    public const NUMBER_TYPES = ['number', 'money', 'percent', 'days'];

    /** Places a worked-out number keeps: enough for any sheet, few enough to drop 0.1 + 0.2 noise. */
    public const PRECISION = 9;

    /** @var array<string, array<int, mixed>> key => tree, in working order */
    private array $trees = [];

    /** @var array<string, true> */
    private array $volatile = [];

    /** @param  array<string, array{formula?: ?string, type?: string, decimals?: ?int, reduce?: ?string}>  $columns */
    public function __construct(private readonly array $columns)
    {
        $known = fn (string $key) => isset($columns[$key]);
        $trees = [];
        foreach ($columns as $key => $c) {
            if (! empty($c['formula'])) {
                $trees[$key] = Formula::parse($c['formula'], fn () => null, $known);
            }
        }
        foreach (self::order($trees) as $key) {
            $this->trees[$key] = $trees[$key];
            if (Formula::usesToday($trees[$key]) || array_any(Formula::refs($trees[$key]), fn ($r) => isset($this->volatile[$r]))) {
                $this->volatile[$key] = true;
            }
        }
    }

    /**
     * Working order: every column after the columns it reads; a loop names
     * the columns in it.
     *
     * @param  array<string, array<int, mixed>>  $trees
     * @return list<string>
     */
    public static function order(array $trees): array
    {
        $out = [];
        $state = [];
        $visit = function (string $key, array $path) use (&$visit, &$out, &$state, $trees): void {
            if (($state[$key] ?? null) === 'done') {
                return;
            }
            if (($state[$key] ?? null) === 'busy') {
                $loop = array_slice($path, array_search($key, $path, true));
                throw new FormulaError('loop', ['columns' => implode(' → ', [...$loop, $key])]);
            }
            $state[$key] = 'busy';
            foreach (Formula::refs($trees[$key]) as $ref) {
                if (isset($trees[$ref])) {
                    $visit($ref, [...$path, $key]);
                }
            }
            $state[$key] = 'done';
            $out[] = $key;
        };
        foreach (array_keys($trees) as $key) {
            $visit($key, []);
        }

        return $out;
    }

    /** @return list<string> formula columns, in working order */
    public function keys(): array
    {
        return array_keys($this->trees);
    }

    public function isFormula(string $key): bool
    {
        return isset($this->trees[$key]);
    }

    public function isVolatile(string $key): bool
    {
        return isset($this->volatile[$key]);
    }

    /**
     * Fill a row's formula columns. `$withVolatile` false leaves the
     * day-dependent ones out (what is stored); true works them too (what is read).
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function apply(array $values, string $today, bool $withVolatile = false): array
    {
        // A formula reads a pairs column (lines of a name and a number) as one number: its lines' total, or its first line.
        $view = $values;
        foreach ($this->columns as $key => $c) {
            if (($c['type'] ?? null) === 'pairs' && array_key_exists($key, $view)) {
                $view[$key] = self::pairsNumber($view[$key], $c['reduce'] ?? 'SUM');
            }
        }
        foreach ($this->trees as $key => $tree) {
            if (! $withVolatile && isset($this->volatile[$key])) {
                unset($values[$key], $view[$key]);

                continue;
            }
            $values[$key] = $view[$key] = $this->shape($key, Formula::evaluate($tree, $view, $today));
        }

        return $values;
    }

    /**
     * The number a pairs column stands for: SUM of its lines' numbers (Color with Qty → total pieces)
     * or the FIRST line's (a price's levels → the summary price); null without a number.
     */
    public static function pairsNumber(mixed $lines, string $reduce = 'SUM'): ?float
    {
        if (! is_array($lines)) {
            return Formula::num($lines);
        }
        $nums = [];
        foreach ($lines as $l) {
            $n = is_array($l) ? Formula::num($l['value'] ?? null) : null;
            if ($n !== null) {
                $nums[] = $n;
            }
        }
        if ($nums === []) {
            return null;
        }

        return $reduce === 'FIRST' ? $nums[0] : array_sum($nums);
    }

    /** A worked-out value as its column holds it. */
    public function shape(string $key, mixed $v): mixed
    {
        $type = $this->columns[$key]['type'] ?? 'text';
        if (Formula::blank($v)) {
            return null;
        }
        if (in_array($type, self::DATE_TYPES, true)) {
            return Formula::day($v) === null ? null : substr((string) $v, 0, 10);
        }
        if (in_array($type, self::NUMBER_TYPES, true)) {
            $n = Formula::num($v);

            // Kept whole, as Excel keeps it (only float noise trimmed): the column's
            // places shape how it shows, and a formula reading it gets the full value.
            return $n === null || is_nan($n) || is_infinite($n) ? null : Formula::round($n, self::PRECISION);
        }

        return Formula::text($v);
    }
}
