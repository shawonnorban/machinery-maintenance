<?php

declare(strict_types=1);

namespace App\Modules\Approval\Actions;

use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Approval\Models\ApprovalRule;
use App\Modules\Approval\Models\ApprovalWorkflow;
use App\Modules\Asset\Models\Asset;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Notification\Services\MaintenanceNotifier;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Raises an approval request against a record (SRS 14).
 *
 * The context is assembled and frozen here. Everything the rules are evaluated
 * against — cost, criticality, factory, maintenance type — is copied into the
 * request, so the chain a job goes through is decided once, from values that
 * are then a matter of record.
 *
 * A work order whose estimate is raised after approval does not silently gain a
 * step it never went through, and does not silently lose one either.
 */
class RequestApproval
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly MaintenanceNotifier $notifier,
    ) {}

    /**
     * Raises approval for a work order, if the configured rules call for any.
     *
     * Returns null when no workflow applies — most routine maintenance needs no
     * signature, and requiring one for a needle change would teach everyone to
     * approve without reading.
     */
    public function forWorkOrder(WorkOrder $workOrder, ?string $userId = null): ?ApprovalRequest
    {
        $existing = ApprovalRequest::where('entity_type', 'WORK_ORDER')
            ->where('entity_id', $workOrder->id)
            ->where('status', 'PENDING')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $workflow = $this->workflowFor('WORK_ORDER');

        if ($workflow === null) {
            return null;
        }

        $context = $this->contextFor($workOrder);

        $applicable = $workflow->rules
            ->filter(fn ($rule) => $rule->appliesTo($context))
            ->values();

        if ($applicable->isEmpty()) {
            // No step matched, so nothing needs signing. Recorded as
            // NOT_REQUIRED rather than approved: they are different facts, and
            // an audit should be able to tell them apart.
            $workOrder->forceFill(['approval_status' => 'NOT_REQUIRED'])->save();

            return null;
        }

        return DB::transaction(function () use ($workflow, $workOrder, $context, $applicable, $userId): ApprovalRequest {
            $request = ApprovalRequest::create([
                'workflow_id' => $workflow->id,
                'entity_type' => 'WORK_ORDER',
                'entity_id' => $workOrder->id,
                'status' => 'PENDING',
                'current_step' => 1,
                'total_steps' => $applicable->count(),
                'requested_by' => $userId,
                'requested_at' => CarbonImmutable::now(),
                // Frozen. A later cost change never alters what was approved.
                'context_json' => $context,
            ]);

            $workOrder->forceFill([
                'approval_status' => 'PENDING',
                'approval_request_id' => $request->id,
            ])->save();

            // Told to whoever can actually sign the first step. A request
            // nobody knows about is a job that stalls for a day and then gets
            // blamed on the workflow.
            $this->notifier->approvalRequested(
                $workOrder,
                $this->approversForFirstStep($applicable->first(), $workOrder->company_id),
                $context['cost'],
            );

            return $request;
        });
    }

    /**
     * The values the rules are evaluated against.
     *
     * Estimated cost is used rather than actual: approval happens before the
     * work, and there is no actual cost yet. Where an estimate is absent the
     * figure is zero, which routes the job down the cheapest chain — stated
     * here because it is a real limitation, not an oversight.
     *
     * @return array<string, mixed>
     */
    private function contextFor(WorkOrder $workOrder): array
    {
        $asset = Asset::find($workOrder->asset_id);

        $cost = bcadd(
            $this->money($workOrder->estimated_labor_cost),
            $this->money($workOrder->estimated_parts_cost),
            4,
        );

        return [
            'cost' => $cost,
            'currency' => $workOrder->currency ?? 'BDT',
            'criticality' => $asset?->criticality,
            'factory_id' => $workOrder->factory_id,
            'maintenance_type_id' => $workOrder->maintenance_type_id,
            'priority' => $workOrder->priority,
            'asset_id' => $workOrder->asset_id,
        ];
    }

    /**
     * The people who can act on the first step, so the notification reaches
     * somebody who can do something rather than everybody who might care.
     *
     * @return Collection<int, User>
     */
    private function approversForFirstStep(?ApprovalRule $rule, string $companyId): Collection
    {
        if ($rule === null) {
            return collect();
        }

        if ($rule->user_id !== null) {
            return User::where('id', $rule->user_id)->get();
        }

        if ($rule->role_id === null) {
            return collect();
        }

        $userIds = UserRole::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $companyId)
            ->where('role_id', $rule->role_id)
            ->pluck('user_id')
            ->unique();

        return User::whereIn('id', $userIds)->where('status', 'ACTIVE')->get();
    }

    private function workflowFor(string $entityType): ?ApprovalWorkflow
    {
        return ApprovalWorkflow::where('entity_type', $entityType)
            ->where('active', true)
            ->with('rules')
            ->first();
    }

    private function money(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 4, '.', '');
    }
}
