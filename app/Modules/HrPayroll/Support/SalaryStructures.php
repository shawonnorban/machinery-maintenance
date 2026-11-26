<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Support;

use App\Modules\HrPayroll\Models\HrEmploymentType;
use App\Modules\HrPayroll\Models\HrGazette;
use App\Modules\HrPayroll\Models\HrGazetteLine;
use App\Modules\HrPayroll\Models\HrPayElement;

/**
 * Salary structures as Quick Registration offers them — a gazette's lines for
 * one employee category ("Worker — RMG Minimum Wage 2023 (Non-EPZ)") — and the
 * split of a gross by them: Basic = (gross − medical − food − conveyance) ÷
 * the Basic divisor, House Rent a share of Basic, the fixed heads as set,
 * and Other whatever rounding leaves.
 */
final class SalaryStructures
{
    /** The fixed heads taken off the gross before Basic is worked out. */
    private const FIXED = ['CONV', 'FOOD', 'MED'];

    /**
     * Every active gazette's structures, by category.
     *
     * @return list<array{value: string, label: string, gazette_id: string, employment_type_id: string, heads: array<string, float>}>
     */
    public static function all(): array
    {
        $gazettes = HrGazette::query()->where('active', true)->orderByDesc('year')->orderBy('name')->get(['id', 'name']);
        $types = HrEmploymentType::query()->pluck('name', 'id');
        $codes = HrPayElement::query()->pluck('code', 'id');
        $lines = HrGazetteLine::query()->whereIn('gazette_id', $gazettes->pluck('id'))->where('active', true)->get()->groupBy(fn ($l) => "{$l->gazette_id}:{$l->employment_type_id}");
        $out = [];
        foreach ($gazettes as $g) {
            foreach ($lines as $key => $set) {
                [$gazette, $type] = explode(':', $key);
                if ($gazette !== $g->id || ! isset($types[$type])) {
                    continue;
                }
                $heads = $set->mapWithKeys(fn ($l) => [$codes[$l->pay_element_id] ?? '' => (float) $l->amount])->filter(fn ($v, $k) => $k !== '')->all();
                if (! isset($heads['BASIC'])) {
                    continue;
                }
                $out[] = ['value' => $key, 'label' => "{$types[$type]} — {$g->name}", 'gazette_id' => $gazette, 'employment_type_id' => $type, 'heads' => $heads];
            }
        }
        // Workers before staff, each in the gazettes' own order (newest first), as Brickland lists them.
        usort($out, fn (array $a, array $b): int => str_starts_with($a['label'], 'Staff') <=> str_starts_with($b['label'], 'Staff'));

        return $out;
    }

    /** One structure by its gazette and category, or null. */
    public static function find(string $gazetteId, string $typeId): ?array
    {
        foreach (self::all() as $s) {
            if ($s['gazette_id'] === $gazetteId && $s['employment_type_id'] === $typeId) {
                return $s;
            }
        }

        return null;
    }

    /**
     * A gross split by a structure's heads, in whole taka.
     *
     * @param  array<string, float>  $heads
     * @return array{basic: int, house_rent: int, conveyance: int, food: int, medical: int, other: int, total: int}
     */
    public static function split(array $heads, float $gross): array
    {
        $fixed = array_sum(array_map(fn (string $c): float => $heads[$c] ?? 0, self::FIXED));
        $basic = max(0, ($gross - $fixed) / ($heads['BASIC'] ?: 1));
        $house = $basic * ($heads['HRENT'] ?? 0) / 100;
        $parts = ['basic' => (int) round($basic), 'house_rent' => (int) round($house), 'conveyance' => (int) round($heads['CONV'] ?? 0), 'food' => (int) round($heads['FOOD'] ?? 0), 'medical' => (int) round($heads['MED'] ?? 0)];

        return $parts + ['other' => (int) round($gross) - array_sum($parts), 'total' => (int) round($gross)];
    }
}
