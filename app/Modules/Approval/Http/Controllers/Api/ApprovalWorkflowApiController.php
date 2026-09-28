<?php

declare(strict_types=1);

namespace App\Modules\Approval\Http\Controllers\Api;

use App\Modules\Approval\Actions\ManageApprovalWorkflow;
use App\Modules\Approval\Models\ApprovalRule;
use App\Modules\Approval\Models\ApprovalWorkflow;
use App\Modules\Asset\Models\Asset;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Who has to sign, and above what, over the wire (SRS 14; mirrors the web
 * `WorkflowController`, which this delegates every write to unchanged via
 * `ManageApprovalWorkflow`, per ADR-003).
 *
 * Deciding who must sign is a company-level decision, not a factory one —
 * every action here sits behind the same permission `settings.company.manage`
 * the web screen uses, with no per-action split.
 */
class ApprovalWorkflowApiController extends ApiController
{
    public function index(): JsonResponse
    {
        $this->allow('settings.company.manage');

        $workflows = ApprovalWorkflow::query()
            ->with(['rules' => fn ($q) => $q->orderBy('sequence'), 'rules.role:id,description'])
            ->orderBy('entity_type')
            ->get();

        $action = app(ManageApprovalWorkflow::class);

        return ApiResponse::ok(
            $workflows->map(fn (ApprovalWorkflow $w): array => $this->summary($w, $action->requestCount($w)))->all(),
        );
    }

    public function store(Request $request, ManageApprovalWorkflow $action): JsonResponse
    {
        $this->allow('settings.company.manage');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'entity_type' => ['required', Rule::in(ApprovalWorkflow::ENTITY_TYPES)],
        ]);

        $workflow = $action->createWorkflow($data);

        return ApiResponse::created($this->summary($workflow, 0));
    }

    public function toggle(ApprovalWorkflow $workflow, ManageApprovalWorkflow $action): JsonResponse
    {
        $this->allow('settings.company.manage');

        $updated = $action->setActive($workflow, ! $workflow->active);

        return ApiResponse::ok($this->summary($updated, $action->requestCount($updated)));
    }

    public function storeRule(Request $request, ApprovalWorkflow $workflow, ManageApprovalWorkflow $action): JsonResponse
    {
        $this->allow('settings.company.manage');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // A Spatie role id — bigint, not this schema's usual ULID.
            'role_id' => ['required', 'integer'],
            'min_cost' => ['nullable', 'numeric', 'min:0'],
            'max_cost' => ['nullable', 'numeric', 'min:0'],
            'criticality' => ['nullable', 'array'],
            'criticality.*' => [Rule::in(Asset::CRITICALITIES)],
            'factory_id' => ['nullable', 'string', 'size:26'],
        ]);

        $rule = $action->addRule($workflow, $data);
        $rule->load('role:id,description');

        return ApiResponse::created($this->ruleSummary($rule));
    }

    public function destroyRule(ApprovalWorkflow $workflow, ApprovalRule $rule, ManageApprovalWorkflow $action): JsonResponse
    {
        $this->allow('settings.company.manage');

        if ($rule->workflow_id !== $workflow->id) {
            abort(404);
        }

        $action->removeRule($rule);

        return ApiResponse::noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(ApprovalWorkflow $workflow, int $requestCount): array
    {
        return [
            'id' => $workflow->id,
            'name' => $workflow->name,
            'entity_type' => $workflow->entity_type,
            'active' => $workflow->active,
            'request_count' => $requestCount,
            'rules' => $workflow->relationLoaded('rules')
                ? $workflow->rules->map(fn (ApprovalRule $rule): array => $this->ruleSummary($rule))->all()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ruleSummary(ApprovalRule $rule): array
    {
        return [
            'id' => $rule->id,
            'workflow_id' => $rule->workflow_id,
            'name' => $rule->name,
            'sequence' => $rule->sequence,
            'role' => $rule->relationLoaded('role') && $rule->role !== null
                ? ['id' => $rule->role->id, 'description' => $rule->role->description]
                : null,
            'conditions' => $rule->condition_json,
        ];
    }
}
