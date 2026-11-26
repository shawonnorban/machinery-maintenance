<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Support;

use App\Modules\Inventory\Models\UnitOfMeasure;
use Closure;

/**
 * The units a quantity may be kept in, for the screens that store a unit as
 * its code (trims, fabric bookings, budgets, challans …) rather than a key:
 * the active units of measure the platform ships plus the company's own
 * (Settings → Master Data → Units of Measure). A form offers these as a
 * pick list; the API accepts a code in any case or a common spelling of it
 * ("pcs", "Kgs", "yards") and stores the master's code.
 */
final class UomCodes
{
    /** Spellings people key that mean a master code. */
    private const ALIASES = [
        'PC' => 'PCS', 'PIECE' => 'PCS', 'PIECES' => 'PCS', 'NOS' => 'PCS',
        'KGS' => 'KG', 'KILO' => 'KG', 'KILOGRAM' => 'KG',
        'YD' => 'YDS', 'YARD' => 'YDS', 'YARDS' => 'YDS',
        'M' => 'MTR', 'METER' => 'MTR', 'METRE' => 'MTR', 'METERS' => 'MTR', 'METRES' => 'MTR',
        'DOZ' => 'DZN', 'DOZEN' => 'DZN', 'LITRE' => 'LTR', 'LITER' => 'LTR',
        'CONES' => 'CONE', 'ROLLS' => 'ROLL', 'SETS' => 'SET', 'PAIRS' => 'PAIR', 'BOXES' => 'BOX', 'LB' => 'LBS',
    ];

    /** @return list<array{code: string, name: string}> */
    public static function options(string $companyId): array
    {
        return UnitOfMeasure::query()->availableTo($companyId)->where('active', true)->orderBy('code')->get(['code', 'name'])
            ->unique('code')->map(fn (UnitOfMeasure $u) => ['code' => $u->code, 'name' => $u->name])->values()->all();
    }

    /** @return list<string> */
    public static function codes(string $companyId): array
    {
        return array_column(self::options($companyId), 'code');
    }

    /** The master's code for what was keyed; null for blank; the trimmed upper-cased text when it names no unit. */
    public static function normalize(?string $value): ?string
    {
        $value = mb_strtoupper(trim((string) $value));
        if ($value === '') {
            return null;
        }

        return self::ALIASES[$value] ?? $value;
    }

    /** A validation rule: blank, or a unit of measure this company may use. */
    public static function rule(string $companyId): Closure
    {
        $codes = null;

        return function (string $attribute, mixed $value, Closure $fail) use ($companyId, &$codes): void {
            $code = self::normalize(is_scalar($value) ? (string) $value : null);
            if ($code === null) {
                return;
            }
            $codes ??= self::codes($companyId);
            if (! in_array($code, $codes, true)) {
                $fail(__('inventory.uom_unknown', ['uom' => (string) $value]));
            }
        };
    }
}
