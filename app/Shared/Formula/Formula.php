<?php

declare(strict_types=1);

namespace App\Shared\Formula;

use DateTimeImmutable;
use DateTimeZone;

/**
 * The spreadsheet formula language of the configurable sheets (Order Summary,
 * AnnotechERP 32; Knit Woven later): Excel-like text a user writes in Column
 * Setup, read and worked out here and — identically — in the browser by
 * frontend/src/lib/formula.js. tests/fixtures/formula_cases.json holds the
 * cases both must agree on; change one side, change the other.
 *
 * Text: `[Column Name]` (as the user writes it) or `{column_key}` (as it is
 * stored), numbers, "text", + - * / &, comparisons = <> < > <= >=, brackets,
 * and the functions in FUNCTIONS.
 *
 * Values: null is blank; numbers are floats; dates are 'Y-m-d' strings.
 * Blank in, blank out — arithmetic, comparisons of order and the date
 * functions give blank when an input is blank (a figure is worked out only
 * once everything it needs is there); a division by zero is blank too. SUM,
 * MIN and MAX skip blanks (blank when all are), N() reads blank as 0,
 * ISBLANK() tests it, JOIN() skips it, IF/AND/OR read it as false.
 *
 * AST nodes: ['n', float] number, ['s', string] text, ['r', key] column,
 * ['f', NAME, [args]] function, ['u', '-', x], ['b', op, left, right].
 */
final class Formula
{
    /** name => [min args, max args or null for any] */
    public const FUNCTIONS = [
        'IF' => [3, 3], 'AND' => [1, null], 'OR' => [1, null], 'NOT' => [1, 1],
        'ROUND' => [1, 2], 'MIN' => [1, null], 'MAX' => [1, null], 'ABS' => [1, 1], 'SUM' => [1, null],
        'N' => [1, 1], 'ISBLANK' => [1, 1], 'TODAY' => [0, 0], 'DAYS' => [2, 2], 'ADD_DAYS' => [2, 2], 'JOIN' => [2, null],
    ];

    /**
     * Read formula text into its tree. `$resolve` turns what is inside [ ] (a
     * column's name) into its key, or null when no column has that name;
     * `{key}` is taken as it is and checked with `$known` (key → bool).
     *
     * @param  callable(string): ?string  $resolve
     * @param  callable(string): bool|null  $known
     * @return array<int, mixed>
     */
    public static function parse(string $source, callable $resolve, ?callable $known = null): array
    {
        $tokens = self::tokens($source);
        $pos = 0;
        $ast = self::comparison($tokens, $pos, $resolve, $known);
        if ($pos < count($tokens)) {
            throw new FormulaError('unexpected', ['text' => $tokens[$pos]['text']], $tokens[$pos]['at']);
        }

        return $ast;
    }

    /** Every column key a tree reads. @return list<string> */
    public static function refs(array $ast): array
    {
        $out = [];
        $walk = function (array $n) use (&$walk, &$out): void {
            match ($n[0]) {
                'r' => $out[$n[1]] = true,
                'f' => array_map($walk, $n[2]),
                'u' => $walk($n[2]),
                'b' => [$walk($n[2]), $walk($n[3])],
                default => null,
            };
        };
        $walk($ast);

        return array_keys($out);
    }

    /** Whether the tree calls TODAY() (its value changes day by day). */
    public static function usesToday(array $ast): bool
    {
        return match ($ast[0]) {
            'f' => $ast[1] === 'TODAY' || array_any($ast[2], fn ($a) => self::usesToday($a)),
            'u' => self::usesToday($ast[2]),
            'b' => self::usesToday($ast[2]) || self::usesToday($ast[3]),
            default => false,
        };
    }

    /** The stored text of a formula the user wrote with names: [Name] → {key}, the rest as written. */
    public static function stored(string $source, callable $resolve): string
    {
        self::parse($source, $resolve);

        return self::rewrite($source, fn (array $tok) => $tok['type'] === 'name' ? '{'.$resolve($tok['value']).'}' : null);
    }

    /** The stored text shown with column names: {key} → [Name]. */
    public static function display(string $stored, callable $label): string
    {
        return self::rewrite($stored, fn (array $tok) => $tok['type'] === 'key' ? '['.($label($tok['value']) ?? $tok['value']).']' : null);
    }

    /** The text with some tokens replaced, spacing and everything else kept. */
    private static function rewrite(string $src, callable $replace): string
    {
        $out = '';
        $from = 0;
        foreach (self::tokens($src) as $tok) {
            $new = $replace($tok);
            if ($new !== null) {
                $out .= substr($src, $from, $tok['at'] - $from).$new;
                $from = $tok['at'] + strlen($tok['text']);
            }
        }

        return $out.substr($src, $from);
    }

    /**
     * Work a tree out against one row's values.
     *
     * @param  array<string, mixed>  $values  key → value
     */
    public static function evaluate(array $ast, array $values, string $today): mixed
    {
        return match ($ast[0]) {
            'n', 's' => $ast[1],
            'r' => self::blank($values[$ast[1]] ?? null) ? null : $values[$ast[1]],
            'u' => ($x = self::num(self::evaluate($ast[2], $values, $today))) === null ? null : -$x,
            'b' => self::binary($ast[1], self::evaluate($ast[2], $values, $today), self::evaluate($ast[3], $values, $today)),
            'f' => self::call($ast[1], $ast[2], $values, $today),
        };
    }

    /** Round half away from zero, the same as lib/formula.js. */
    public static function round(float $x, int $places): float
    {
        $f = 10 ** $places;
        $v = floor(abs($x) * $f + 0.5 + 1e-9) / $f;

        return $x < 0 ? -$v : $v;
    }

    public static function blank(mixed $v): bool
    {
        return $v === null || $v === '' || (is_array($v) && $v === []);
    }

    /* ── Evaluation ─────────────────────────────────────────────────── */

    private static function binary(string $op, mixed $a, mixed $b): mixed
    {
        if ($op === '&') {
            return self::text($a).self::text($b);
        }
        if ($op === '=' || $op === '<>') {
            $same = self::equal($a, $b);

            return $op === '=' ? $same : ! $same;
        }
        if (in_array($op, ['<', '>', '<=', '>='], true)) {
            if (self::blank($a) || self::blank($b)) {
                return null;
            }
            $x = self::num($a);
            $y = self::num($b);
            $c = ($x !== null && $y !== null) ? $x <=> $y : strcmp((string) $a, (string) $b) <=> 0;

            return match ($op) { '<' => $c < 0, '>' => $c > 0, '<=' => $c <= 0, '>=' => $c >= 0 };
        }
        $x = self::num($a);
        $y = self::num($b);
        if ($x === null || $y === null) {
            return null;
        }

        return match ($op) {
            '+' => $x + $y,
            '-' => $x - $y,
            '*' => $x * $y,
            '/' => $y == 0.0 ? null : $x / $y,
        };
    }

    private static function call(string $name, array $args, array $values, string $today): mixed
    {
        $ev = fn (array $a) => self::evaluate($a, $values, $today);
        switch ($name) {
            case 'IF':
                return self::truthy($ev($args[0])) ? $ev($args[1]) : $ev($args[2]);
            case 'AND':
                foreach ($args as $a) {
                    if (! self::truthy($ev($a))) {
                        return false;
                    }
                }

                return true;
            case 'OR':
                foreach ($args as $a) {
                    if (self::truthy($ev($a))) {
                        return true;
                    }
                }

                return false;
            case 'NOT':
                return ! self::truthy($ev($args[0]));
            case 'TODAY':
                return $today;
            case 'ISBLANK':
                return self::blank($ev($args[0]));
            case 'N':
                return self::num($ev($args[0])) ?? 0.0;
            case 'ABS':
                $x = self::num($ev($args[0]));

                return $x === null ? null : abs($x);
            case 'ROUND':
                $x = self::num($ev($args[0]));
                $p = isset($args[1]) ? self::num($ev($args[1])) : 0.0;

                return ($x === null || $p === null) ? null : self::round($x, (int) $p);
            case 'SUM':
            case 'MIN':
            case 'MAX':
                $nums = array_values(array_filter(array_map(fn ($a) => self::num($ev($a)), $args), fn ($v) => $v !== null));
                if ($nums === []) {
                    return null;
                }

                return match ($name) { 'SUM' => array_sum($nums), 'MIN' => min($nums), 'MAX' => max($nums) };
            case 'DAYS':
                $a = self::day($ev($args[0]));
                $b = self::day($ev($args[1]));

                return ($a === null || $b === null) ? null : (float) ($a - $b);
            case 'ADD_DAYS':
                $d = self::day($ev($args[0]));
                $n = self::num($ev($args[1]));

                return ($d === null || $n === null) ? null : self::date($d + (int) $n);
            case 'JOIN':
                $sep = self::text($ev($args[0]));
                $parts = array_values(array_filter(array_map(fn ($a) => self::text($ev($a)), array_slice($args, 1)), fn ($s) => $s !== ''));

                return implode($sep, $parts);
        }

        throw new FormulaError('unknown_function', ['name' => $name]);
    }

    private static function truthy(mixed $v): bool
    {
        if (self::blank($v)) {
            return false;
        }
        if (is_bool($v)) {
            return $v;
        }
        $n = self::num($v);

        return $n === null ? true : $n != 0.0;
    }

    private static function equal(mixed $a, mixed $b): bool
    {
        if (self::blank($a) || self::blank($b)) {
            return self::blank($a) && self::blank($b);
        }
        $x = self::num($a);
        $y = self::num($b);
        if ($x !== null && $y !== null) {
            return abs($x - $y) < 1e-9;
        }

        return mb_strtolower(trim(self::text($a))) === mb_strtolower(trim(self::text($b)));
    }

    /** A number, or null for blank / not a number. */
    public static function num(mixed $v): ?float
    {
        if (is_bool($v)) {
            return $v ? 1.0 : 0.0;
        }
        if (is_int($v) || is_float($v)) {
            return is_nan((float) $v) ? null : (float) $v;
        }
        if (is_string($v)) {
            $s = str_replace(',', '', trim($v));

            return $s !== '' && is_numeric($s) ? (float) $s : null;
        }

        return null;
    }

    public static function text(mixed $v): string
    {
        if (self::blank($v)) {
            return '';
        }
        if (is_bool($v)) {
            return $v ? 'TRUE' : 'FALSE';
        }
        if (is_float($v) || is_int($v)) {
            $s = rtrim(rtrim(sprintf('%.10F', (float) $v), '0'), '.');

            return $s === '-0' ? '0' : $s;
        }
        if (is_array($v)) {
            return implode(', ', array_map(fn ($x) => self::text($x), $v));
        }

        return (string) $v;
    }

    /** Day number of a 'Y-m-d' date (days since 1970-01-01), or null. */
    public static function day(mixed $v): ?int
    {
        if (! is_string($v) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        return intdiv((new DateTimeImmutable("{$m[1]}-{$m[2]}-{$m[3]}", new DateTimeZone('UTC')))->getTimestamp(), 86400);
    }

    public static function date(int $day): string
    {
        return gmdate('Y-m-d', $day * 86400);
    }

    /* ── Reading ────────────────────────────────────────────────────── */

    /** @return list<array{type: string, text: string, at: int, value?: mixed}> */
    private static function tokens(string $src): array
    {
        $out = [];
        $len = strlen($src);
        $i = 0;
        while ($i < $len) {
            $c = $src[$i];
            if (ctype_space($c)) {
                $i++;

                continue;
            }
            $at = $i;
            if ($c === '[') {
                $end = strpos($src, ']', $i);
                if ($end === false) {
                    throw new FormulaError('unclosed_name', [], $at);
                }
                $out[] = ['type' => 'name', 'text' => substr($src, $i, $end - $i + 1), 'value' => trim(substr($src, $i + 1, $end - $i - 1)), 'at' => $at];
                $i = $end + 1;
            } elseif ($c === '{') {
                $end = strpos($src, '}', $i);
                if ($end === false) {
                    throw new FormulaError('unclosed_name', [], $at);
                }
                $out[] = ['type' => 'key', 'text' => substr($src, $i, $end - $i + 1), 'value' => trim(substr($src, $i + 1, $end - $i - 1)), 'at' => $at];
                $i = $end + 1;
            } elseif ($c === '"') {
                $end = strpos($src, '"', $i + 1);
                if ($end === false) {
                    throw new FormulaError('unclosed_text', [], $at);
                }
                $out[] = ['type' => 'str', 'text' => substr($src, $i, $end - $i + 1), 'value' => substr($src, $i + 1, $end - $i - 1), 'at' => $at];
                $i = $end + 1;
            } elseif (ctype_digit($c) || ($c === '.' && $i + 1 < $len && ctype_digit($src[$i + 1]))) {
                preg_match('/\G\d*\.?\d+(?:[eE][+-]?\d+)?|\G\d+\.?/', $src, $m, 0, $i);
                $out[] = ['type' => 'num', 'text' => $m[0], 'value' => (float) $m[0], 'at' => $at];
                $i += strlen($m[0]);
            } elseif (ctype_alpha($c) || $c === '_') {
                preg_match('/\G[A-Za-z_][A-Za-z0-9_]*/', $src, $m, 0, $i);
                $out[] = ['type' => 'ident', 'text' => $m[0], 'value' => strtoupper($m[0]), 'at' => $at];
                $i += strlen($m[0]);
            } elseif (in_array(substr($src, $i, 2), ['<=', '>=', '<>'], true)) {
                $out[] = ['type' => 'op', 'text' => substr($src, $i, 2), 'at' => $at];
                $i += 2;
            } elseif (str_contains('+-*/=<>&(),', $c)) {
                $out[] = ['type' => str_contains('(),', $c) ? $c : 'op', 'text' => $c, 'at' => $at];
                $i++;
            } else {
                throw new FormulaError('bad_character', ['text' => mb_substr(substr($src, $i), 0, 1)], $at);
            }
        }

        return $out;
    }

    private static function comparison(array $t, int &$p, callable $r, ?callable $k): array
    {
        $left = self::additive($t, $p, $r, $k);
        while (isset($t[$p]) && $t[$p]['type'] === 'op' && in_array($t[$p]['text'], ['=', '<>', '<', '>', '<=', '>='], true)) {
            $op = $t[$p++]['text'];
            $left = ['b', $op, $left, self::additive($t, $p, $r, $k)];
        }

        return $left;
    }

    private static function additive(array $t, int &$p, callable $r, ?callable $k): array
    {
        $left = self::term($t, $p, $r, $k);
        while (isset($t[$p]) && $t[$p]['type'] === 'op' && in_array($t[$p]['text'], ['+', '-', '&'], true)) {
            $op = $t[$p++]['text'];
            $left = ['b', $op, $left, self::term($t, $p, $r, $k)];
        }

        return $left;
    }

    private static function term(array $t, int &$p, callable $r, ?callable $k): array
    {
        $left = self::unary($t, $p, $r, $k);
        while (isset($t[$p]) && $t[$p]['type'] === 'op' && in_array($t[$p]['text'], ['*', '/'], true)) {
            $op = $t[$p++]['text'];
            $left = ['b', $op, $left, self::unary($t, $p, $r, $k)];
        }

        return $left;
    }

    private static function unary(array $t, int &$p, callable $r, ?callable $k): array
    {
        if (isset($t[$p]) && $t[$p]['type'] === 'op' && in_array($t[$p]['text'], ['-', '+'], true)) {
            $op = $t[$p++]['text'];
            $x = self::unary($t, $p, $r, $k);

            return $op === '-' ? ['u', '-', $x] : $x;
        }

        return self::primary($t, $p, $r, $k);
    }

    private static function primary(array $t, int &$p, callable $r, ?callable $k): array
    {
        $tok = $t[$p] ?? null;
        if ($tok === null) {
            throw new FormulaError('unexpected_end');
        }
        $p++;
        switch ($tok['type']) {
            case 'num':
                return ['n', $tok['value']];
            case 'str':
                return ['s', $tok['value']];
            case 'name':
                $key = $r($tok['value']);
                if ($key === null) {
                    throw new FormulaError('unknown_column', ['name' => $tok['value']], $tok['at']);
                }

                return ['r', $key];
            case 'key':
                if ($k !== null && ! $k($tok['value'])) {
                    throw new FormulaError('unknown_column', ['name' => $tok['value']], $tok['at']);
                }

                return ['r', $tok['value']];
            case '(':
                $inner = self::comparison($t, $p, $r, $k);
                self::expect($t, $p, ')');

                return $inner;
            case 'ident':
                $name = $tok['value'];
                if (! isset(self::FUNCTIONS[$name])) {
                    throw new FormulaError('unknown_function', ['name' => $tok['text']], $tok['at']);
                }
                self::expect($t, $p, '(');
                $args = [];
                if (($t[$p]['type'] ?? null) !== ')') {
                    $args[] = self::comparison($t, $p, $r, $k);
                    while (($t[$p]['type'] ?? null) === ',') {
                        $p++;
                        $args[] = self::comparison($t, $p, $r, $k);
                    }
                }
                self::expect($t, $p, ')');
                [$min, $max] = self::FUNCTIONS[$name];
                if (count($args) < $min || ($max !== null && count($args) > $max)) {
                    throw new FormulaError('arg_count', ['name' => $name, 'min' => $min, 'max' => $max ?? '…'], $tok['at']);
                }

                return ['f', $name, $args];
        }

        throw new FormulaError('unexpected', ['text' => $tok['text']], $tok['at']);
    }

    private static function expect(array $t, int &$p, string $type): void
    {
        if (($t[$p]['type'] ?? null) !== $type) {
            throw new FormulaError('expected', ['text' => $type], $t[$p]['at'] ?? null);
        }
        $p++;
    }
}
