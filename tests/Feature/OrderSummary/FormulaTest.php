<?php

declare(strict_types=1);

namespace Tests\Feature\OrderSummary;

use App\Shared\Formula\Formula;
use App\Shared\Formula\FormulaError;
use App\Shared\Formula\FormulaSet;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The server's formula engine against frontend/src/lib/formula.cases.json —
 * the same cases the browser's engine (lib/formula.js) runs, so a figure
 * typed in the sheet and the one saved never differ. No database needed.
 */
class FormulaTest extends TestCase
{
    private static function cases(): array
    {
        return json_decode((string) file_get_contents(dirname(__DIR__, 3).'/frontend/src/lib/formula.cases.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    public static function evalCases(): iterable
    {
        foreach (self::cases()['eval'] as $c) {
            yield $c['name'] => [$c];
        }
    }

    public static function errorCases(): iterable
    {
        foreach (self::cases()['errors'] as $c) {
            yield $c['name'] => [$c];
        }
    }

    public static function setCases(): iterable
    {
        foreach (self::cases()['sets'] as $c) {
            yield $c['name'] => [$c];
        }
    }

    #[DataProvider('evalCases')]
    public function test_eval(array $c): void
    {
        $known = array_flip(self::cases()['known']);
        $ast = Formula::parse($c['formula'], fn () => null, fn ($k) => isset($known[$k]) || array_key_exists($k, $c['values']));
        $actual = Formula::evaluate($ast, $c['values'], self::cases()['today']);
        if (is_int($c['expect']) || is_float($c['expect'])) {
            $this->assertEqualsWithDelta($c['expect'], $actual, 1e-9);
        } else {
            $this->assertSame($c['expect'], $actual);
        }
    }

    #[DataProvider('errorCases')]
    public function test_refuses(array $c): void
    {
        $known = array_flip(self::cases()['known']);
        try {
            Formula::parse($c['formula'], fn () => null, fn ($k) => isset($known[$k]));
            $this->fail('not refused');
        } catch (FormulaError $e) {
            $this->assertSame($c['kind'], $e->kind);
        }
    }

    #[DataProvider('setCases')]
    public function test_set(array $c): void
    {
        if (isset($c['error'])) {
            $this->expectException(FormulaError::class);
            new FormulaSet($c['columns']);

            return;
        }
        $set = new FormulaSet($c['columns']);
        $this->assertSame($c['volatile'], array_values(array_filter($set->keys(), fn ($k) => $set->isVolatile($k))));
        $this->assertEquals($c['stored'], $set->apply($c['values'], self::cases()['today']));
        $this->assertEquals($c['read'], $set->apply($c['values'], self::cases()['today'], true));
    }

    public function test_names_become_keys_and_back_with_spacing_kept(): void
    {
        $byName = ['Quantity (Ticket)' => 'quantity_ticket', 'PAC' => 'pac'];
        $stored = Formula::stored('[Quantity (Ticket)] *  [PAC] & "[PAC]"', fn ($n) => $byName[$n] ?? null);
        $this->assertSame('{quantity_ticket} *  {pac} & "[PAC]"', $stored);
        $this->assertSame('[Quantity (Ticket)] *  [PAC] & "[PAC]"', Formula::display($stored, fn ($k) => array_flip($byName)[$k] ?? null));

        try {
            Formula::stored('[PAC] + [Nope]', fn ($n) => $byName[$n] ?? null);
            $this->fail('not refused');
        } catch (FormulaError $e) {
            $this->assertSame('unknown_column', $e->kind);
            $this->assertSame(8, $e->position);
        }
    }
}
