<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Http\Controllers\Api\Concerns;

use App\Modules\OrderSummary\Support\OrderScope;
use App\Modules\PreBudget\Models\PbBudget;
use App\Modules\PreBudget\Models\PbChange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The budgets a user reaches (their Merchandising Team's buyers, and their own;
 * Store everything for stock), one found by its number, the edit lock and the
 * budget's change log — shared by the budget and BOM controllers.
 */
trait FindsBudgets
{
    /** @return list<string>|null */
    protected function buyerScope(): ?array
    {
        $user = $this->caller()->auditUserId();

        return $user === null ? null : OrderScope::buyerIds($user, $this->caller()->can('prebudget.view_all'));
    }

    /** The budgets this user reaches: their buyers', and the ones they made; Store (stock screens) every one. */
    protected function scoped(bool $stock = false): Builder
    {
        $query = PbBudget::query();
        if ($stock && $this->caller()->can('prebudget.stock')) {
            return $query;
        }
        $scope = $this->buyerScope();
        if ($scope !== null) {
            $me = $this->caller()->auditUserId();
            $signs = $this->signedBudgetIds();
            $query->where(fn ($q) => $q->whereIn('buyer_id', $scope)->orWhere('created_by', $me)->orWhereIn('id', $signs));
        }

        return $query;
    }

    /**
     * The budgets this user signs or signed in the Approval Center (a step of
     * the PRE_BUDGET chain names them or their role, or they acted
     * on it) — a CFO or MD outside the merchandising team still opens what
     * they are asked to approve.
     *
     * @return list<string>
     */
    protected function signedBudgetIds(): array
    {
        $me = $this->caller()->auditUserId();
        if ($me === null) {
            return [];
        }
        $roles = DB::table('user_roles')->where('company_id', $this->caller()->companyId)->where('user_id', $me)->pluck('role_id')->all();
        $workflows = DB::table('approval_rules as r')->join('approval_workflows as w', 'w.id', '=', 'r.workflow_id')->where('w.entity_type', 'PRE_BUDGET')
            ->where(fn ($q) => $q->where('r.user_id', $me)->orWhereIn('r.role_id', $roles ?: ['-'])
                ->orWhereExists(fn ($e) => $e->from('approval_rule_users as u')->whereColumn('u.rule_id', 'r.id')->where('u.user_id', $me)))
            ->pluck('r.workflow_id')->unique()->all();

        return DB::table('approval_requests')->where('entity_type', 'PRE_BUDGET')
            ->where(fn ($q) => $q->whereIn('workflow_id', $workflows ?: ['-'])
                ->orWhereExists(fn ($e) => $e->from('approval_actions as a')->whereColumn('a.approval_request_id', 'approval_requests.id')->where('a.approver_id', $me)))
            ->pluck('entity_id')->unique()->values()->all();
    }

    /**
     * A budget to look at: whoever holds one of `$perms` finds it as usual;
     * a signer of its Approval Center chain without them (CFO, MD …) may open
     * it too; anyone else is refused.
     */
    protected function readable(string $label, array $perms, bool $stock = false): PbBudget
    {
        foreach ($perms as $p) {
            if ($this->caller()->can($p)) {
                return $this->find($label, $stock);
            }
        }
        if ($this->caller()->can('approval.ticket.create')) {
            [$no, $rev] = preg_match('/^(.*)-R(\d+)$/', $label, $m) ? [$m[1], (int) $m[2]] : [$label, 0];
            $b = PbBudget::query()->where('budget_no', $no)->where('revision_no', $rev)->first();
            if ($b && in_array($b->id, $this->signedBudgetIds(), true)) {
                return $b;
            }
        }

        throw \App\Shared\Http\Api\ApiException::of(\App\Shared\Http\Api\ErrorCode::FORBIDDEN);
    }

    /** A budget by its number (PB-CNA-1126-0001, a revision PB-…-R1). */
    protected function find(string $label, bool $stock = false): PbBudget
    {
        $rev = 0;
        if (preg_match('/^(.*)-R(\d+)$/', $label, $m)) {
            [$label, $rev] = [$m[1], (int) $m[2]];
        }

        return $this->scoped($stock)->where('budget_no', $label)->where('revision_no', $rev)->first() ?? abort(404);
    }

    protected function editable(PbBudget $b): void
    {
        if (! $b->editable()) {
            throw ValidationException::withMessages(['budget' => __('prebudget.budget.v.locked')]);
        }
    }

    /** @param  array<string, array{0: mixed, 1: mixed}>  $changes */
    protected function log(PbBudget $b, string $kind, array $changes, ?string $by): void
    {
        $batch = (string) Str::ulid();
        $base = ['company_id' => $b->company_id, 'subject' => 'BUDGET', 'subject_id' => $b->id, 'buyer_id' => $b->buyer_id, 'kind' => $kind, 'batch' => $batch, 'changed_by' => $by, 'changed_at' => now()];
        $rows = [];
        foreach ($changes as $key => [$old, $new]) {
            $rows[] = ['id' => (string) Str::ulid(), ...$base, 'column_key' => mb_substr((string) $key, 0, 64),
                'old_value' => $old === null ? null : mb_substr((string) $old, 0, 5000), 'new_value' => $new === null ? null : mb_substr((string) $new, 0, 5000)];
        }
        if ($rows === []) {
            $rows[] = ['id' => (string) Str::ulid(), ...$base, 'column_key' => null, 'old_value' => null, 'new_value' => null];
        }
        PbChange::query()->insert($rows);
        \App\Modules\Erp\Support\SheetNotices::note('pb', $batch); // told to the team once the save is done (user, 2026-11-21)
    }
}
