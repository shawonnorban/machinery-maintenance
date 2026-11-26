<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Support;

use App\Modules\Approval\Actions\DecideApproval;
use App\Modules\Approval\Actions\RequestApproval;
use App\Modules\Approval\Models\ApprovalAction;
use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Approval\Models\ApprovalRule;
use App\Modules\Approval\Models\ApprovalWorkflow;
use App\Modules\Identity\Models\User;
use App\Modules\PreBudget\Models\PbBudget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A pre-budget's way through the Approval Center (Pre-Budget step 6, user
 * 2026-11-19): the PRE_BUDGET chain — Merchandising Head → CFO → MD, made
 * for a company the first time a budget is finalized, then the company's to
 * change in Approval Center → Approval Workflow (who signs each step, a step
 * switched off …) — and where a budget stands on it: each level with who may
 * sign it and who did, the history of every round, and whether this user may
 * act now.
 */
final class BudgetApproval
{
    public const ENTITY = 'PRE_BUDGET';

    /** The starting chain: one level each, no amount bands (no value-based steps, user 2026-11-19). */
    public const STEPS = ['Merchandising Head', 'CFO', 'MD'];

    /** The company's active PRE_BUDGET chain, made from STEPS when it has never had one; null when it switched it off. */
    public static function workflow(string $companyId): ?ApprovalWorkflow
    {
        if (! ApprovalWorkflow::query()->where('entity_type', self::ENTITY)->exists()) {
            DB::transaction(function () use ($companyId): void {
                $w = ApprovalWorkflow::query()->create([
                    'company_id' => $companyId, 'name' => 'Pre-Budget Approval', 'entity_type' => self::ENTITY, 'active' => true,
                    'type_code' => 'PRE-BUDGET', 'short_name' => 'PB', 'document_type' => 'Pre-Budget', 'workflow_type' => 'APPROVED_BY_END_USER',
                    'page_url' => '/pre-budget/budget-finalized',
                ]);
                foreach (self::STEPS as $i => $name) {
                    ApprovalRule::query()->create([
                        'company_id' => $companyId, 'workflow_id' => $w->id, 'sequence' => $i + 1, 'level' => $i + 1, 'name' => $name, 'active' => true,
                        'requires_signature' => false, 'can_repeat' => false, 'can_cancel' => true, 'can_resend' => true,
                    ]);
                }
            });
        }

        return ApprovalWorkflow::query()->where('entity_type', self::ENTITY)->where('active', true)->with('rules')->first();
    }

    /** @return Collection<int, ApprovalRule> the chain's signing steps, in order */
    public static function steps(ApprovalWorkflow $w): Collection
    {
        return $w->rules->filter(fn (ApprovalRule $r) => $r->active && $r->level >= 1)->sortBy('sequence')->values();
    }

    /** @return list<string> the steps nobody can sign yet (no person, role or team set in Approval Workflow) */
    public static function unassigned(ApprovalWorkflow $w, string $companyId): array
    {
        $req = app(RequestApproval::class);

        return self::steps($w)->filter(fn (ApprovalRule $r) => $req->approversFor($r, $companyId)->isEmpty())->map(fn (ApprovalRule $r) => $r->name ?: "Level {$r->level}")->values()->all();
    }

    /**
     * Where the budget stands: its request's levels (or the chain's, before it
     * is sent), the history of every round, and what this user may do.
     *
     * @return array<string, mixed>
     */
    public function info(PbBudget $b, ?User $user): array
    {
        $req = app(RequestApproval::class);
        $request = $b->approval_request_id ? ApprovalRequest::query()->find($b->approval_request_id) : null;
        $rules = $request ? $request->applicableRules() : (($w = ApprovalWorkflow::query()->where('entity_type', self::ENTITY)->where('active', true)->with('rules')->first()) ? self::steps($w) : collect());
        $actions = $request ? ApprovalAction::query()->where('approval_request_id', $request->id)->orderBy('acted_at')->orderBy('id')->get() : collect();
        $names = $this->names(array_merge($actions->pluck('approver_id')->all(), [$b->submitted_by, $b->approved_by], $request ? [$request->requested_by] : []));
        $levels = [];
        foreach ($rules->values() as $i => $rule) {
            $step = $i + 1;
            $done = $actions->first(fn ($a) => $a->step === $step && in_array($a->action, ['APPROVED', 'REJECTED', 'RETURNED'], true));
            $state = $done ? $done->action : ($request && $request->isPending() && $request->current_step === $step ? 'CURRENT' : 'WAITING');
            $levels[] = [
                'level' => $step, 'name' => $rule->name ?: "Level {$step}", 'state' => $state,
                'approvers' => $req->approversFor($rule, $b->company_id)->pluck('name')->values()->all(),
                'by' => $done ? ($names[$done->approver_id] ?? null) : null, 'at' => $done?->acted_at?->toIso8601String(), 'comment' => $done?->comment,
            ];
        }
        // Every round of this budget (sent, sent back, sent again …), newest last.
        $rounds = ApprovalRequest::query()->where('entity_type', self::ENTITY)->where('entity_id', $b->id)->pluck('id');
        $history = ApprovalAction::query()->whereIn('approval_request_id', $rounds)->orderBy('acted_at')->orderBy('id')->get();
        $names += $this->names($history->pluck('approver_id')->all());

        return [
            'status' => $request?->status, 'step' => $request?->current_step, 'steps' => $request?->total_steps,
            'current' => $request && $request->isPending() ? ($levels[$request->current_step - 1]['name'] ?? null) : null,
            'levels' => $levels,
            'history' => $history->map(fn (ApprovalAction $a) => ['action' => $a->action, 'step' => $a->step, 'by' => $names[$a->approver_id] ?? null, 'at' => $a->acted_at?->toIso8601String(), 'comment' => $a->comment])->all(),
            'submitted_at' => $b->submitted_at?->toIso8601String(), 'submitted_by' => $names[$b->submitted_by] ?? null,
            'approved_at' => $b->approved_at?->toIso8601String(), 'approved_by' => $names[$b->approved_by] ?? null,
            'can_act' => $request !== null && $user !== null && app(DecideApproval::class)->canAct($request, $user),
        ];
    }

    /** @param  list<string|null>  $ids  @return array<string, string> */
    private function names(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));

        return $ids === [] ? [] : User::query()->whereIn('id', $ids)->pluck('name', 'id')->all();
    }
}
