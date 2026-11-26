<?php

declare(strict_types=1);

namespace App\Modules\ExportStatement\Support;

use App\Modules\Approval\Actions\DecideApproval;
use App\Modules\Approval\Actions\RequestApproval;
use App\Modules\Approval\Models\ApprovalAction;
use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Approval\Models\ApprovalRule;
use App\Modules\Approval\Models\ApprovalWorkflow;
use App\Modules\ExportStatement\Models\EsEditRequest;
use App\Modules\ExportStatement\Models\ExportStatement;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * An edit of a saved export statement goes through the Approval Center (user,
 * 2026-11-21; Norban sent it to the editor's department head): it waits as an
 * EsEditRequest, the statement keeping its values (status Pending Approval),
 * and is written onto the statement on the last approval; a rejected edit
 * leaves the statement as it was, status included. One edit waits at a time:
 * someone else's edit of a waiting statement is refused; the editor's own
 * new edit replaces their waiting one, and putting the values back withdraws
 * it. Whoever holds `exportstatement.approve` saves an edit at once (Norban's
 * department head and Super Admin).
 *
 * The chain is the company's EXPORT_STATEMENT_EDIT workflow, made with one
 * step (Department Head) the first time it is needed; who signs each step is
 * set in Approval Center → Approval Workflow. A company that switches the
 * chain off saves edits at once.
 */
final class EsApproval
{
    public const ENTITY = 'EXPORT_STATEMENT_EDIT';

    public const STEPS = ['Department Head'];

    public const SAVED = 'saved';

    public const PENDING = 'pending';

    public const UNCHANGED = 'unchanged';

    /** The company's active chain, made from STEPS when it has never had one; null when it switched it off. */
    public static function workflow(string $companyId): ?ApprovalWorkflow
    {
        if (! ApprovalWorkflow::query()->where('entity_type', self::ENTITY)->exists()) {
            DB::transaction(function () use ($companyId): void {
                $w = ApprovalWorkflow::query()->create([
                    'company_id' => $companyId, 'name' => 'Export Statement Edit Approval', 'entity_type' => self::ENTITY, 'active' => true,
                    'type_code' => 'EXPORT-STATEMENT', 'short_name' => 'ES', 'document_type' => 'Export Statement Edit', 'workflow_type' => 'APPROVED_BY_END_USER',
                    'page_url' => '/export-statement/approvals',
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

    /** @return list<string> the chain's steps nobody can sign yet */
    public static function unassigned(ApprovalWorkflow $w, string $companyId): array
    {
        $req = app(RequestApproval::class);

        return $w->rules->filter(fn (ApprovalRule $r) => $r->active && $r->level >= 1)->sortBy('sequence')
            ->filter(fn (ApprovalRule $r) => $req->approversFor($r, $companyId)->isEmpty())->map(fn (ApprovalRule $r) => $r->name ?: "Level {$r->level}")->values()->all();
    }

    /**
     * An edit of a saved statement: written at once (`$direct`), or held for
     * approval. `$next`/`$diff` are EsSheet::put()'s answer; `$field` names
     * the row in an error.
     *
     * @param  array<string, mixed>  $next
     * @param  array<string, array{0: mixed, 1: mixed}>  $diff
     * @return string SAVED | PENDING | UNCHANGED
     */
    public static function edit(EsSheet $sheet, ExportStatement $s, array $next, array $diff, ?string $by, bool $direct, string $batch, string $field, string $source = 'EDIT'): string
    {
        $pending = $s->pendingEdit();
        if ($pending && $pending->requested_by !== $by) {
            throw ValidationException::withMessages([$field => __('exportstatement.v.pending_other', [
                'invoice' => $s->values['invoice_no'] ?? '—', 'name' => $pending->requester?->name ?? '—',
            ])]);
        }
        if ($diff === []) {
            if ($pending) {
                self::withdraw($pending, $by, __('exportstatement.approval.withdrawn_same'));
            }

            return self::UNCHANGED;
        }
        if ($pending) {
            self::withdraw($pending, $by, __('exportstatement.approval.replaced'));
            $s->refresh();
        }
        if ($direct) {
            $s->forceFill([
                'values' => $next, 'buyer_id' => $sheet->buyerId($next), 'status' => ExportStatement::APPROVED, 'approved_by' => $by, 'approved_at' => now(),
                'version' => $s->version + 1, 'updated_by' => $by,
            ])->save();
            $sheet->log($s, 'EDIT', $diff, $batch, $by);

            return self::SAVED;
        }

        $workflow = self::workflow($s->company_id);
        if ($workflow && ($nobody = self::unassigned($workflow, $s->company_id)) !== []) {
            throw ValidationException::withMessages([$field => __('exportstatement.v.unassigned', ['steps' => implode(', ', $nobody)])]);
        }
        $edit = EsEditRequest::query()->create(['company_id' => $s->company_id, 'statement_id' => $s->id, 'diff' => $diff, 'source' => $source, 'status' => EsEditRequest::PENDING, 'requested_by' => $by]);
        $s->forceFill(['status' => ExportStatement::PENDING, 'version' => $s->version + 1])->save();
        app(RequestApproval::class)->forEntity($edit, $by);

        return $edit->fresh()->status === EsEditRequest::PENDING ? self::PENDING : self::SAVED;
    }

    /** Approved (or no step to sign): the edit's typed cells onto the statement as it stands now, formulas worked out again. */
    public static function apply(EsEditRequest $e, ?string $by): void
    {
        DB::transaction(function () use ($e, $by): void {
            $s = ExportStatement::query()->whereKey($e->statement_id)->lockForUpdate()->first();
            if (! $s) {
                $e->forceFill(['status' => EsEditRequest::CANCELLED, 'decided_by' => $by, 'decided_at' => now()])->save();

                return;
            }
            $sheet = new EsSheet($s->company_id);
            $next = $s->values ?? [];
            foreach ((array) $e->diff as $key => $pair) {
                $c = $sheet->column((string) $key);
                if ($c && $c->type !== 'system' && ! $sheet->formulas->isFormula($c->key)) {
                    $next[$key] = is_array($pair) ? ($pair[1] ?? null) : null;
                }
            }
            $next = array_filter($sheet->formulas->apply($next, EsSheet::today()), fn ($v) => $v !== null && $v !== '' && $v !== []);
            $diff = $sheet->diff($s->values ?? [], $next);
            $s->forceFill([
                'values' => $next, 'buyer_id' => $sheet->buyerId($next), 'status' => ExportStatement::APPROVED, 'approved_by' => $by, 'approved_at' => now(),
                'version' => $s->version + 1, 'updated_by' => $e->requested_by,
            ])->save();
            if ($diff !== []) {
                $sheet->log($s, 'EDIT', $diff, (string) Str::ulid(), $e->requested_by, false); // the Approval Center tells this one
            }
            $e->forceFill(['status' => EsEditRequest::APPROVED, 'decided_by' => $by, 'decided_at' => now(), 'decision_note' => self::lastComment($e)])->save();
        });
    }

    /** Rejected or withdrawn: the statement keeps its values and goes back to the status it had. */
    public static function close(EsEditRequest $e, string $status, ?string $by): void
    {
        $e->forceFill(['status' => $status, 'decided_by' => $by, 'decided_at' => now(), 'decision_note' => self::lastComment($e)])->save();
        $s = ExportStatement::query()->whereKey($e->statement_id)->first();
        if ($s && $s->status === ExportStatement::PENDING && ! $s->pendingEdit()) {
            $s->forceFill(['status' => $s->approved_at ? ExportStatement::APPROVED : ExportStatement::CREATED, 'version' => $s->version + 1])->save();
        }
    }

    /** The editor takes their waiting edit back (or replaces it). */
    public static function withdraw(EsEditRequest $e, ?string $by, ?string $comment = null): void
    {
        $request = $e->approval_request_id ? ApprovalRequest::query()->find($e->approval_request_id) : null;
        if ($request && $request->isPending()) {
            app(DecideApproval::class)->cancel($request, $by, $comment);
        }
        if ($e->fresh()->status === EsEditRequest::PENDING) {
            self::close($e, EsEditRequest::CANCELLED, $by);
        }
    }

    /**
     * Where a waiting edit stands: the step now, who may sign it, and whether
     * this user may act.
     *
     * @return array{step: ?int, steps: ?int, current: ?string, approvers: list<string>, can_act: bool}
     */
    public static function info(EsEditRequest $e, ?User $user): array
    {
        $request = $e->approval_request_id ? ApprovalRequest::query()->find($e->approval_request_id) : null;
        if (! $request) {
            return ['step' => null, 'steps' => null, 'current' => null, 'approvers' => [], 'can_act' => false];
        }
        $rule = $request->isPending() ? $request->applicableRules()->values()->get($request->current_step - 1) : null;

        return [
            'step' => $request->current_step, 'steps' => $request->total_steps, 'current' => $rule ? ($rule->name ?: "Level {$rule->level}") : null,
            'approvers' => $rule ? app(RequestApproval::class)->approversFor($rule, $e->company_id)->pluck('name')->values()->all() : [],
            'can_act' => $user !== null && $request->isPending() && app(DecideApproval::class)->canAct($request, $user),
        ];
    }

    private static function lastComment(EsEditRequest $e): ?string
    {
        return $e->approval_request_id
            ? ApprovalAction::query()->where('approval_request_id', $e->approval_request_id)->whereNotNull('comment')->latest('acted_at')->value('comment')
            : null;
    }

    /** @param  list<string|null>  $ids  @return Collection<string, string> */
    public static function names(array $ids): Collection
    {
        $ids = array_values(array_unique(array_filter($ids)));

        return $ids === [] ? collect() : User::query()->whereIn('id', $ids)->pluck('name', 'id');
    }
}
