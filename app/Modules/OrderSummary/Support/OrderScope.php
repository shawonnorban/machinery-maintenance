<?php

declare(strict_types=1);

namespace App\Modules\OrderSummary\Support;

use App\Modules\Merchandise\Models\BuyerUserPermission;
use App\Modules\OrderSummary\Models\OsDepartmentHead;
use App\Modules\OrderSummary\Models\OsTeam;
use App\Modules\OrderSummary\Models\OsTeamMember;
use Illuminate\Support\Collection;

/**
 * Whose orders a user sees and edits in Order Summary (AnnotechERP 32, the
 * user's decision of 2026-11-18): a merchandiser the buyers assigned to them
 * (Merchandise Desk's Buyer Account Permission, kind MERCHANDISING); a team
 * head every buyer of their team's merchandisers; a department head every
 * buyer of every team in their department. `ordersummary.view_all` sees all.
 * The same rules pick who receives an order's edit mail.
 */
final class OrderScope
{
    /** Buyer ids the user may work, or null for every buyer. @return list<string>|null */
    public static function buyerIds(string $userId, bool $viewAll): ?array
    {
        if ($viewAll) {
            return null;
        }

        return self::buyersOf(self::merchandisersUnder($userId)->push($userId)->unique());
    }

    /**
     * The merchandisers a user leads: members of the teams they head, and of
     * every team in the departments they head.
     *
     * @return Collection<int, string>
     */
    public static function merchandisersUnder(string $userId): Collection
    {
        $departments = OsDepartmentHead::query()->where('user_id', $userId)->pluck('department_id');
        $teams = OsTeam::query()->where('active', true)
            ->where(fn ($q) => $q->where('team_head_id', $userId)->orWhereIn('department_id', $departments))
            ->pluck('id');

        return OsTeamMember::query()->whereIn('team_id', $teams)->pluck('user_id')->values();
    }

    /** @param  Collection<int, string>  $users  @return list<string> */
    public static function buyersOf(Collection $users): array
    {
        return BuyerUserPermission::query()->where('kind', 'MERCHANDISING')->whereIn('user_id', $users)->pluck('buyer_id')->unique()->values()->all();
    }

    /**
     * Who hears of an edit to an order of this buyer: its merchandisers, the
     * heads of their teams and the heads of those teams' departments.
     *
     * @return list<string> user ids
     */
    public static function recipientsFor(string $buyerId): array
    {
        $merchandisers = BuyerUserPermission::query()->where('kind', 'MERCHANDISING')->where('buyer_id', $buyerId)->pluck('user_id');
        $teams = OsTeam::query()->where('active', true)->whereIn('id', OsTeamMember::query()->whereIn('user_id', $merchandisers)->select('team_id'))->get(['team_head_id', 'department_id']);
        $deptHeads = OsDepartmentHead::query()->whereIn('department_id', $teams->pluck('department_id')->filter())->pluck('user_id');

        return $merchandisers->merge($teams->pluck('team_head_id')->filter())->merge($deptHeads)->unique()->values()->all();
    }
}
