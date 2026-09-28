<?php

declare(strict_types=1);

namespace App\Modules\Approval\Actions;

use App\Modules\Approval\Contracts\Approvable;
use App\Modules\Approval\Models\ApprovalAction;
use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Approval\Models\ApprovalRule;
use App\Modules\Approval\Support\ApprovalEntities;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Notification\Services\MaintenanceNotifier;
use App\Shared\Scopes\TenantScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Approving and rejecting (SRS 14, ERD Section 20).
 *
 * Three guards, and each one exists because of a specific way approval
 * otherwise becomes theatre:
 *
 * 1. The requester may never approve their own request. An approval you can
 *    grant yourself is a checkbox, not a control.
 *
 * 2. The approver must satisfy the current step. Otherwise anyone with the
 *    approvals screen open can sign anything, and the workflow's ordering
 *    means nothing.
 *
 * 3. Every decision writes an action row. "Who approved this" must stay
 *    answerable after the person has left the company.
 */
class DecideApproval
{
    public function __construct(private readonly MaintenanceNotifier $notifier) {}

    public function approve(ApprovalRequest $request, User $approver, ?string $comment = null): ApprovalRequest
    {
        $this->assertActionable($request, $approver);

        return DB::transaction(function () use ($request, $approver, $comment): ApprovalRequest {
            $this->record($request, $approver, 'APPROVED', $comment);

            $isFinalStep = $request->current_step >= $request->total_steps;

            if (! $isFinalStep) {
                // On to whoever is next. The record stays pending, because it
                // is not approved until the last signature.
                $request->forceFill(['current_step' => $request->current_step + 1])->save();

                return $request->fresh();
            }

            $request->forceFill([
                'status' => 'APPROVED',
                'completed_at' => CarbonImmutable::now(),
            ])->save();

            $this->applyToEntity($request->fresh(), 'APPROVED');

            return $request->fresh();
        });
    }

    /**
     * A rejection ends the request outright rather than stepping back.
     *
     * Sending it to the previous approver would mean the same person signing
     * the same job twice with no record of what changed in between.
     */
    public function reject(ApprovalRequest $request, User $approver, string $comment): ApprovalRequest
    {
        if (blank($comment)) {
            // A refusal with no reason gives the requester nothing to act on,
            // and the job simply gets resubmitted unchanged.
            throw ValidationException::withMessages([
                'comment' => __('approval.rejection_needs_reason'),
            ]);
        }

        $this->assertActionable($request, $approver);

        return DB::transaction(function () use ($request, $approver, $comment): ApprovalRequest {
            $this->record($request, $approver, 'REJECTED', $comment);

            $request->forceFill([
                'status' => 'REJECTED',
                'completed_at' => CarbonImmutable::now(),
            ])->save();

            $this->applyToEntity($request->fresh(), 'REJECTED');

            return $request->fresh();
        });
    }

    public function cancel(ApprovalRequest $request, ?string $userId = null, ?string $comment = null): ApprovalRequest
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'status' => __('approval.not_pending'),
            ])->status(409);
        }

        return DB::transaction(function () use ($request, $userId, $comment): ApprovalRequest {
            ApprovalAction::create([
                'approval_request_id' => $request->id,
                'approver_id' => $userId,
                'step' => $request->current_step,
                'action' => 'CANCELLED',
                'comment' => $comment,
                'acted_at' => CarbonImmutable::now(),
            ]);

            $request->forceFill([
                'status' => 'CANCELLED',
                'completed_at' => CarbonImmutable::now(),
            ])->save();

            // The engine's status resets, but the document is told it was
            // cancelled, not that nothing was required: a requisition must
            // not move itself to APPROVED because its request was withdrawn.
            $this->applyToEntity($request->fresh(), 'NOT_REQUIRED', 'CANCELLED');

            return $request->fresh();
        });
    }

    /**
     * Expires requests nobody acted on.
     *
     * Recorded as an action rather than a silent status flip: a job that
     * stalled because nobody looked at it for a fortnight is a fact worth
     * having in the history.
     *
     * @return int the number expired
     */
    public function expireOverdue(): int
    {
        $expired = 0;

        ApprovalRequest::where('status', 'PENDING')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', CarbonImmutable::now())
            ->get()
            ->each(function (ApprovalRequest $request) use (&$expired): void {
                DB::transaction(function () use ($request): void {
                    ApprovalAction::create([
                        'approval_request_id' => $request->id,
                        'approver_id' => null,
                        'step' => $request->current_step,
                        'action' => 'EXPIRED',
                        'comment' => __('approval.expired_automatically'),
                        'acted_at' => CarbonImmutable::now(),
                    ]);

                    $request->forceFill([
                        'status' => 'EXPIRED',
                        'completed_at' => CarbonImmutable::now(),
                    ])->save();

                    $this->applyToEntity($request->fresh(), 'REJECTED');
                });

                $expired++;
            });

        return $expired;
    }

    /**
     * Whether this user may act on the step the request is currently at.
     */
    public function canAct(ApprovalRequest $request, User $approver): bool
    {
        if (! $request->isPending()) {
            return false;
        }

        if ($request->requested_by === $approver->id) {
            return false;
        }

        $rule = $request->currentRule();

        if ($rule === null) {
            return false;
        }

        return $this->satisfies($rule, $approver, $request->company_id);
    }

    private function assertActionable(ApprovalRequest $request, User $approver): void
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'status' => __('approval.not_pending'),
            ])->status(409);
        }

        if ($request->requested_by === $approver->id) {
            throw ValidationException::withMessages([
                'approver_id' => __('approval.cannot_approve_own_request'),
            ])->status(403);
        }

        $rule = $request->currentRule();

        if ($rule === null) {
            throw ValidationException::withMessages([
                'status' => __('approval.no_current_step'),
            ])->status(409);
        }

        if (! $this->satisfies($rule, $approver, $request->company_id)) {
            throw ValidationException::withMessages([
                'approver_id' => __('approval.not_your_step'),
            ])->status(403);
        }
    }

    private function satisfies(ApprovalRule $rule, User $approver, string $companyId): bool
    {
        if ($rule->user_id !== null) {
            return $rule->user_id === $approver->id;
        }

        if ($rule->role_id !== null) {
            // Read unscoped and filtered explicitly: the approver may be acting
            // in a company the request belongs to rather than their own default.
            return UserRole::withoutGlobalScope(TenantScope::class)
                ->where('company_id', $companyId)
                ->where('user_id', $approver->id)
                ->where('role_id', $rule->role_id)
                ->exists();
        }

        if ($rule->team_id !== null) {
            return DB::table('team_members')
                ->where('team_id', $rule->team_id)
                ->where('user_id', $approver->id)
                ->exists();
        }

        // A step naming nobody is a misconfiguration, and treating it as open
        // to everyone would let one bad row disable the whole control.
        return false;
    }

    private function record(ApprovalRequest $request, User $approver, string $action, ?string $comment): void
    {
        ApprovalAction::create([
            'approval_request_id' => $request->id,
            'approver_id' => $approver->id,
            'step' => $request->current_step,
            'action' => $action,
            'comment' => $comment,
            'acted_at' => CarbonImmutable::now(),
        ]);
    }

    /**
     * Writes the verdict onto the document the request was raised for.
     *
     * Every routable model carries an `approval_status`; the ones that
     * implement Approvable (requisitions, purchase orders) also move their own
     * workflow status and are told so the requester hears about it. A work
     * order reads its `approval_status` column instead, as it always has.
     */
    private function applyToEntity(ApprovalRequest $request, string $status, ?string $verdict = null): void
    {
        $entity = ApprovalEntities::find($request->entity_type, $request->entity_id);

        if ($entity === null) {
            return;
        }

        $entity->forceFill(['approval_status' => $status])->save();

        if (! $entity instanceof Approvable) {
            return;
        }

        $verdict ??= $status;

        $entity->onApprovalDecided($verdict);

        if (in_array($verdict, ['APPROVED', 'REJECTED'], true)) {
            $this->notifier->approvalDecided(
                $entity->approvalSummary(),
                $request->entity_type,
                $entity->id,
                $request->requested_by === null ? null : User::find($request->requested_by),
                $verdict,
            );
        }
    }
}
