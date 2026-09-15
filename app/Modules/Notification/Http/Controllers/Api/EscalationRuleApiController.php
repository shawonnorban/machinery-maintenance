<?php

declare(strict_types=1);

namespace App\Modules\Notification\Http\Controllers\Api;

use App\Modules\Notification\Actions\ManageEscalationRule;
use App\Modules\Notification\Models\EscalationRule;
use App\Modules\Notification\Models\Notification;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Who gets told when nobody answers, over the wire (SRS 28; mirrors the web
 * `EscalationRuleController`, which this delegates every write to unchanged
 * via `ManageEscalationRule`, per ADR-003).
 *
 * Same permission as Approval's workflow config — `settings.company.manage`,
 * no per-action split — since this is company-level configuration too.
 */
class EscalationRuleApiController extends ApiController
{
    public function index(): JsonResponse
    {
        $this->allow('settings.company.manage');

        $rules = EscalationRule::query()
            ->with(['role:id,description', 'factory:id,name'])
            ->orderBy('event_type')
            ->orderBy('escalation_level')
            ->get();

        return ApiResponse::ok($rules->map(fn (EscalationRule $rule): array => $this->summary($rule))->all());
    }

    public function store(Request $request, ManageEscalationRule $action): JsonResponse
    {
        $this->allow('settings.company.manage');

        $data = $request->validate([
            'event_type' => ['required', Rule::in(Notification::EVENT_TYPES)],
            'severity' => ['nullable', Rule::in(Notification::SEVERITIES)],
            'factory_id' => ['nullable', 'string', 'size:26'],
            'delay_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'escalation_level' => ['required', 'integer', 'min:1', 'max:5'],
            // A Spatie role id — bigint, not this schema's usual ULID.
            'escalation_role_id' => ['required', 'integer'],
            'max_escalations' => ['nullable', 'integer', 'min:1', 'max:10'],
            'stop_on_acknowledge' => ['sometimes', 'boolean'],
        ]) + ['stop_on_acknowledge' => $request->boolean('stop_on_acknowledge', true)];

        $rule = $action->create($data);
        $rule->load(['role:id,description', 'factory:id,name']);

        return ApiResponse::created($this->summary($rule));
    }

    public function toggle(EscalationRule $rule, ManageEscalationRule $action): JsonResponse
    {
        $this->allow('settings.company.manage');

        $updated = $action->setActive($rule, ! $rule->active);
        $updated->load(['role:id,description', 'factory:id,name']);

        return ApiResponse::ok($this->summary($updated));
    }

    public function destroy(EscalationRule $rule, ManageEscalationRule $action): JsonResponse
    {
        $this->allow('settings.company.manage');

        $action->delete($rule);

        return ApiResponse::noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(EscalationRule $rule): array
    {
        return [
            'id' => $rule->id,
            'event_type' => $rule->event_type,
            'severity' => $rule->severity,
            'delay_minutes' => $rule->delay_minutes,
            'escalation_level' => $rule->escalation_level,
            'role' => $rule->relationLoaded('role') && $rule->role !== null
                ? ['id' => $rule->role->id, 'description' => $rule->role->description]
                : null,
            'factory' => $rule->relationLoaded('factory') && $rule->factory !== null
                ? ['id' => $rule->factory->id, 'name' => $rule->factory->name]
                : null,
            'max_escalations' => $rule->max_escalations,
            'stop_on_acknowledge' => $rule->stop_on_acknowledge,
            'active' => $rule->active,
        ];
    }
}
