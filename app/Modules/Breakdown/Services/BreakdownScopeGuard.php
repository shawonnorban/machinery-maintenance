<?php

declare(strict_types=1);

namespace App\Modules\Breakdown\Services;

use App\Modules\Identity\Models\User;
use App\Modules\WorkOrder\Models\Technician;
use Illuminate\Support\Facades\Gate;

/**
 * Whether a person may report or repair a breakdown against a given
 * location — the hard line/department restriction the factory chose over
 * ADR-065's advisory-only stance for work-order assignment.
 *
 * ADR-065 still governs *offering* a technician for a work order: whoever
 * covers a line is suggested first, but a manager may send anyone. This
 * guard governs something narrower and stricter — a breakdown itself,
 * reported and repaired by whoever's floor it happened on. The factory's
 * own workflow puts a name on every line and department, so nothing here
 * is optional the way a work-order assignment dropdown is.
 *
 * Two kinds of people carry a coverage area: a Technician (via
 * `department_id`/`production_line_id`), and a non-technician role that
 * still owns a patch of floor — today that's the Line Chief, via the same
 * pair of columns added to `users` for exactly this. A manager or engineer
 * (anyone holding `breakdown.breakdown.assign`) is exempt outright: at two
 * in the morning, someone has to be able to reassign a breakdown to
 * whoever is actually awake.
 */
final class BreakdownScopeGuard
{
    /**
     * @return array{department_id: ?string, production_line_id: ?string}|null
     *               null means "no personal coverage area" — unrestricted.
     */
    public static function coverageFor(User $user): ?array
    {
        $technician = Technician::forUser($user);

        if ($technician !== null) {
            return [
                'department_id' => $technician->department_id,
                'production_line_id' => $technician->production_line_id,
            ];
        }

        if ($user->department_id !== null || $user->production_line_id !== null) {
            return [
                'department_id' => $user->department_id,
                'production_line_id' => $user->production_line_id,
            ];
        }

        return null;
    }

    /**
     * @param  array{department_id: ?string, production_line_id: ?string}|null  $coverage
     */
    public static function covers(?array $coverage, ?string $departmentId, ?string $productionLineId): bool
    {
        if ($coverage === null) {
            return true;
        }

        if ($coverage['production_line_id'] !== null) {
            return $coverage['production_line_id'] === $productionLineId;
        }

        if ($coverage['department_id'] !== null) {
            return $coverage['department_id'] === $departmentId;
        }

        return true;
    }

    /** Whoever may already assign or reassign a breakdown is not narrowed by their own coverage area. */
    public static function isExempt(User $user): bool
    {
        return Gate::forUser($user)->allows('breakdown.breakdown.assign');
    }

    public static function assertCovers(User $user, ?string $departmentId, ?string $productionLineId): bool
    {
        if (self::isExempt($user)) {
            return true;
        }

        return self::covers(self::coverageFor($user), $departmentId, $productionLineId);
    }
}
