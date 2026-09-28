<?php

declare(strict_types=1);

namespace App\Modules\Approval\Actions;

use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Approval\Models\ApprovalRule;
use App\Modules\Approval\Models\ApprovalWorkflow;
use App\Modules\Identity\Models\Role;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Who has to sign, and above what (SRS 14).
 *
 * Approvals worked from the day they were built, against rules nobody could
 * write: a factory got whatever the seed happened to say. This is the other
 * half — the thresholds themselves.
 *
 * Rules are ordered, and the order is the chain: a job over 100,000 taka goes
 * to the factory manager and then to the owner, in that sequence. Each rule
 * names a role rather than a person, because a chain that names Karim stops
 * working the week Karim is on leave.
 *
 * A rule is never edited once requests have been raised against its workflow —
 * the context each request froze is what its approver agreed to, and changing
 * the rule behind it would make the record disagree with itself. Instead the
 * rule is removed and a new one added, which is visible as a change rather
 * than a silent rewrite.
 */
class ManageApprovalWorkflow
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createWorkflow(array $data): ApprovalWorkflow
    {
        return ApprovalWorkflow::create([
            'company_id' => $this->context->companyId(),
            'name' => $data['name'],
            'entity_type' => $data['entity_type'],
            'active' => true,
        ]);
    }

    public function setActive(ApprovalWorkflow $workflow, bool $active): ApprovalWorkflow
    {
        $workflow->forceFill(['active' => $active])->save();

        return $workflow->fresh();
    }

    /**
     * Add a step to the chain.
     *
     * @param  array<string, mixed>  $data
     */
    public function addRule(ApprovalWorkflow $workflow, array $data): ApprovalRule
    {
        $role = Role::availableTo($this->context->companyId())->find($data['role_id'] ?? null);

        if ($role === null) {
            throw ValidationException::withMessages([
                'role_id' => __('approval.unknown_role'),
            ]);
        }

        $conditions = $this->conditionsFrom($data);

        if ($conditions === []) {
            // A rule with no condition matches everything, which would send a
            // needle change to the company owner.
            throw ValidationException::withMessages([
                'min_cost' => __('approval.rule_needs_a_condition'),
            ])->status(422);
        }

        $next = (int) ApprovalRule::where('workflow_id', $workflow->id)->max('sequence') + 1;

        return ApprovalRule::create([
            'company_id' => $this->context->companyId(),
            'workflow_id' => $workflow->id,
            'name' => $data['name'],
            'role_id' => $role->id,
            'condition_json' => $conditions,
            'sequence' => $next,
        ]);
    }

    public function removeRule(ApprovalRule $rule): void
    {
        DB::transaction(function () use ($rule): void {
            $workflowId = $rule->workflow_id;

            $rule->delete();

            // Resequenced so the chain has no gaps: the numbers are the order
            // signatures are collected in, not labels.
            ApprovalRule::where('workflow_id', $workflowId)
                ->orderBy('sequence')
                ->get()
                ->each(fn (ApprovalRule $row, int $index) => $row->forceFill(['sequence' => $index + 1])->save());
        });
    }

    /**
     * Whether anything has ever been asked of this workflow.
     *
     * Shown rather than enforced: a factory may legitimately change its chain,
     * and the requests already raised keep the context they froze.
     */
    public function requestCount(ApprovalWorkflow $workflow): int
    {
        return ApprovalRequest::where('workflow_id', $workflow->id)->count();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function conditionsFrom(array $data): array
    {
        $conditions = [];

        foreach (['min_cost', 'max_cost'] as $bound) {
            if (filled($data[$bound] ?? null)) {
                $conditions[$bound] = number_format((float) $data[$bound], 4, '.', '');
            }
        }

        foreach (['criticality', 'priority'] as $field) {
            $values = array_values(array_filter((array) ($data[$field] ?? [])));

            if ($values !== []) {
                $conditions[$field] = $values;
            }
        }

        if (filled($data['factory_id'] ?? null)) {
            $conditions['factory_id'] = $data['factory_id'];
        }

        return $conditions;
    }
}
