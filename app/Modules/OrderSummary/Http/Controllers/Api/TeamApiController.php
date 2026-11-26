<?php

declare(strict_types=1);

namespace App\Modules\OrderSummary\Http\Controllers\Api;

use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\BuyerUserPermission;
use App\Modules\OrderSummary\Models\OsDepartmentHead;
use App\Modules\OrderSummary\Models\OsTeam;
use App\Modules\OrderSummary\Models\OsTeamMember;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Order Summary → Merchandising Team (AnnotechERP 32): department heads,
 * and teams — a department, a team head and the merchandisers, each with the
 * buyers they work. A merchandiser's buyers are Merchandise Desk's Buyer
 * Account Permission (kind MERCHANDISING), so both screens show the same.
 * These say whose orders each person sees and whose edits they are mailed.
 */
class TeamApiController extends ApiController
{
    private const MANAGE = 'ordersummary.team.manage';

    public function index(): JsonResponse
    {
        $this->allow('ordersummary.view');
        $c = $this->caller()->companyId;
        $buyersOf = BuyerUserPermission::query()->where('kind', 'MERCHANDISING')->get(['user_id', 'buyer_id'])->groupBy('user_id')->map(fn ($r) => $r->pluck('buyer_id')->values()->all());
        $members = OsTeamMember::query()->get(['team_id', 'user_id'])->groupBy('team_id');

        return ApiResponse::ok([
            'departments' => DB::table('departments')->where('company_id', $c)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($d) => ['value' => $d->id, 'label' => $d->code ? "{$d->name} ({$d->code})" : $d->name])->all(),
            'users' => $this->users(),
            'buyers' => Buyer::query()->where('active', true)->orderBy('short_name')->get(['id', 'short_name', 'name'])->map(fn ($b) => ['value' => $b->id, 'label' => $b->short_name ?: $b->name])->all(),
            'heads' => OsDepartmentHead::query()->get(['department_id', 'user_id'])->groupBy('department_id')->map(fn ($r) => $r->pluck('user_id')->values()->all()),
            'teams' => OsTeam::query()->orderBy('name')->get()->map(fn (OsTeam $t) => [
                'id' => $t->id, 'name' => $t->name, 'department_id' => $t->department_id, 'team_head_id' => $t->team_head_id, 'active' => $t->active,
                'members' => ($members[$t->id] ?? collect())->map(fn ($m) => ['user_id' => $m->user_id, 'buyer_ids' => $buyersOf[$m->user_id] ?? []])->values()->all(),
            ])->all(),
            'can_manage' => $this->caller()->can(self::MANAGE),
        ]);
    }

    /** A department's heads, replaced. */
    public function heads(Request $request): JsonResponse
    {
        $this->allow(self::MANAGE);
        $c = $this->caller()->companyId;
        $data = $request->validate([
            'department_id' => ['required', Rule::exists('departments', 'id')->where('company_id', $c)],
            'user_ids' => ['present', 'array'], 'user_ids.*' => ['string', Rule::in(array_column($this->users(), 'value'))],
        ], [], __('ordersummary.team.attrs'));
        DB::transaction(function () use ($data): void {
            OsDepartmentHead::query()->where('department_id', $data['department_id'])->delete();
            foreach (array_unique($data['user_ids']) as $u) {
                OsDepartmentHead::query()->create(['department_id' => $data['department_id'], 'user_id' => $u]);
            }
        });

        return ApiResponse::ok(['department_id' => $data['department_id']]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow(self::MANAGE);

        return ApiResponse::created(['id' => $this->save($request, null)->id]);
    }

    public function update(Request $request, string $team): JsonResponse
    {
        $this->allow(self::MANAGE);

        return ApiResponse::ok(['id' => $this->save($request, OsTeam::query()->findOrFail($team))->id]);
    }

    public function destroy(string $team): JsonResponse
    {
        $this->allow(self::MANAGE);
        OsTeam::query()->findOrFail($team)->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    /**
     * A team with its merchandisers; each merchandiser's buyers replace what
     * they had (one person's buyers are the same in every team they are in).
     */
    private function save(Request $request, ?OsTeam $row): OsTeam
    {
        $c = $this->caller()->companyId;
        $users = array_column($this->users(), 'value');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('os_teams', 'name')->where('company_id', $c)->ignore($row?->id)],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('company_id', $c)],
            'team_head_id' => ['nullable', Rule::in($users)],
            'active' => ['sometimes', 'boolean'],
            'members' => ['present', 'array'], 'members.*.user_id' => ['required', 'distinct', Rule::in($users)],
            'members.*.buyer_ids' => ['present', 'array'], 'members.*.buyer_ids.*' => ['string', Rule::exists('buyers', 'id')->where('company_id', $c)],
        ], [], __('ordersummary.team.attrs'));

        return DB::transaction(function () use ($data, $row): OsTeam {
            $team = $row ?? new OsTeam;
            $team->fill(['name' => trim($data['name']), 'department_id' => $data['department_id'] ?? null, 'team_head_id' => $data['team_head_id'] ?? null, 'active' => $data['active'] ?? true])->save();
            OsTeamMember::query()->where('team_id', $team->id)->delete();
            foreach ($data['members'] as $m) {
                OsTeamMember::query()->create(['team_id' => $team->id, 'user_id' => $m['user_id']]);
                BuyerUserPermission::query()->where('kind', 'MERCHANDISING')->where('user_id', $m['user_id'])->whereNotIn('buyer_id', $m['buyer_ids'])->delete();
                foreach (array_unique($m['buyer_ids']) as $b) {
                    BuyerUserPermission::query()->firstOrCreate(['kind' => 'MERCHANDISING', 'user_id' => $m['user_id'], 'buyer_id' => $b]);
                }
            }

            return $team;
        });
    }

    /** The company's active users. @return list<array{value: string, label: string}> */
    private function users(): array
    {
        return DB::table('company_users as cu')->join('users as u', 'u.id', '=', 'cu.user_id')
            ->where('cu.company_id', $this->caller()->companyId)->where('cu.status', 'ACTIVE')->orderBy('u.name')
            ->get(['u.id', 'u.name', 'u.email'])->map(fn ($u) => ['value' => $u->id, 'label' => "{$u->name} ({$u->email})"])->all();
    }
}
