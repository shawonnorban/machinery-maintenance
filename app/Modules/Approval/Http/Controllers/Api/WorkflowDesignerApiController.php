<?php

declare(strict_types=1);

namespace App\Modules\Approval\Http\Controllers\Api;

use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Approval\Models\ApprovalRule;
use App\Modules\Approval\Models\ApprovalRuleUser;
use App\Modules\Approval\Models\ApprovalWorkflow;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Access Control → Approval Workflow Designer (AnnotechERP 02/03): action
 * types (chains), their actions (steps) in order, and the named people each
 * step lands on.
 *
 * A chain with requests still waiting on it keeps its shape: steps may be
 * renamed, but not added, removed, reordered, re-levelled or re-banded, since
 * a waiting request counts its levels by position.
 */
class WorkflowDesignerApiController extends ApiController
{
    public const WORKFLOW_TYPES = ['APPROVED_BY_END_USER', 'PURCHASE_REQUISITION'];

    public function index(): JsonResponse
    {
        $this->allow('approval.type.manage');

        $rows = ApprovalWorkflow::query()->withCount('rules')->orderBy('type_code')->orderBy('name')->get()
            ->map(fn (ApprovalWorkflow $w): array => $this->workflowRow($w) + ['actions' => $w->rules_count])->all();

        return ApiResponse::ok($rows, ['options' => $this->options()]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('approval.type.manage');

        $data = $request->validate($this->workflowRules(null));
        $data['type_code'] = strtoupper($data['type_code'] ?? '') ?: $this->nextCode();
        $workflow = ApprovalWorkflow::query()->create($data + ['company_id' => $this->caller()->companyId, 'active' => $data['active'] ?? true]);

        return ApiResponse::created($this->workflowRow($workflow));
    }

    public function update(Request $request, string $workflow): JsonResponse
    {
        $this->allow('approval.type.manage');

        $row = ApprovalWorkflow::query()->whereKey($workflow)->firstOrFail();
        $data = $request->validate($this->workflowRules($row));
        if (isset($data['type_code'])) {
            $data['type_code'] = strtoupper($data['type_code']);
        }
        $row->fill($data)->save();

        return ApiResponse::ok($this->workflowRow($row));
    }

    public function steps(string $workflow): JsonResponse
    {
        $this->allow('approval.type.manage');

        $row = ApprovalWorkflow::query()->whereKey($workflow)->firstOrFail();

        return ApiResponse::ok(['workflow' => $this->workflowRow($row), 'steps' => $this->stepRows($row), 'pending' => $this->pendingOn($row)]);
    }

    /** The whole list at once: what is sent is the chain. */
    public function saveSteps(Request $request, string $workflow): JsonResponse
    {
        $this->allow('approval.type.manage');

        $row = ApprovalWorkflow::query()->whereKey($workflow)->firstOrFail();
        $company = $this->caller()->companyId;
        $data = $request->validate([
            'steps' => ['present', 'array', 'max:50'],
            'steps.*.id' => ['nullable', 'string'],
            'steps.*.sequence' => ['required', 'integer', 'min:1', 'max:999'],
            'steps.*.name' => ['required', 'string', 'max:255'],
            'steps.*.task_message' => ['nullable', 'string', 'max:255'],
            // The company's own roles and the platform's, never another company's.
            'steps.*.role_id' => ['nullable', Rule::in(Role::query()->availableTo($company)->pluck('id')->all())],
            'steps.*.role_flag' => ['nullable', 'string', 'max:255'],
            'steps.*.next_action' => ['nullable', 'string', 'max:255'],
            'steps.*.level' => ['required', 'integer', 'min:0', 'max:20'],
            'steps.*.min_cost' => ['nullable', 'numeric', 'min:0'],
            'steps.*.max_cost' => ['nullable', 'numeric', 'min:0'],
            'steps.*.requires_signature' => ['boolean'],
            'steps.*.can_repeat' => ['boolean'],
            'steps.*.can_cancel' => ['boolean'],
            'steps.*.can_resend' => ['boolean'],
            'steps.*.active' => ['boolean'],
        ]);

        $existing = ApprovalRule::query()->where('workflow_id', $row->id)->get()->keyBy('id');
        foreach ($data['steps'] as $i => $step) {
            if (! empty($step['id']) && ! $existing->has($step['id'])) {
                throw ValidationException::withMessages(["steps.{$i}.id" => __('approval.center.unknown_step')]);
            }
            if (isset($step['min_cost'], $step['max_cost']) && (float) $step['min_cost'] > (float) $step['max_cost']) {
                throw ValidationException::withMessages(["steps.{$i}.max_cost" => __('approval.center.band_order')]);
            }
        }

        if ($this->pendingOn($row) > 0 && $this->shapeOf($existing->values()->map(fn (ApprovalRule $r) => $this->shapeRow($r->toArray() + $this->bandOf($r)))->all()) !== $this->shapeOf(array_map(fn ($s) => $this->shapeRow($s), $data['steps']))) {
            throw ValidationException::withMessages(['steps' => __('approval.center.chain_in_use')]);
        }

        DB::transaction(function () use ($data, $existing, $row, $company): void {
            $kept = [];
            foreach ($data['steps'] as $step) {
                $band = array_filter(['min_cost' => $step['min_cost'] ?? null, 'max_cost' => $step['max_cost'] ?? null], fn ($v) => $v !== null && $v !== '');
                $rule = ! empty($step['id']) ? $existing->get($step['id']) : new ApprovalRule(['company_id' => $company, 'workflow_id' => $row->id]);
                // Other conditions written by older screens are kept; only the band is the designer's.
                $conditions = array_diff_key($rule->condition_json ?? [], ['min_cost' => 1, 'max_cost' => 1]) + $band;
                $rule->fill([
                    'sequence' => $step['sequence'], 'name' => $step['name'], 'task_message' => $step['task_message'] ?? null,
                    'role_id' => $step['role_id'] ?? null, 'role_flag' => $step['role_flag'] ?? null, 'next_action' => $step['next_action'] ?? null,
                    'level' => $step['level'], 'condition_json' => $conditions === [] ? null : $conditions,
                    'requires_signature' => (bool) ($step['requires_signature'] ?? false), 'can_repeat' => (bool) ($step['can_repeat'] ?? false),
                    'can_cancel' => (bool) ($step['can_cancel'] ?? false), 'can_resend' => (bool) ($step['can_resend'] ?? false),
                    'active' => (bool) ($step['active'] ?? true),
                ])->save();
                $kept[] = $rule->id;
            }
            ApprovalRule::query()->where('workflow_id', $row->id)->whereNotIn('id', $kept)->delete();
        });

        return $this->steps($workflow);
    }

    /** Action Assignment: everyone in the company, with whoever the step lands on ticked. */
    public function stepUsers(string $rule): JsonResponse
    {
        $this->allow('approval.type.manage');

        $step = ApprovalRule::query()->whereKey($rule)->firstOrFail();
        $assigned = ApprovalRuleUser::query()->where('rule_id', $step->id)->pluck('user_id')->flip();
        $members = CompanyUser::query()->where('company_id', $this->caller()->companyId)->where('status', 'ACTIVE')->pluck('user_id');

        return ApiResponse::ok(User::query()->with('department')->whereIn('id', $members)->orderBy('name')->get()
            ->map(fn (User $u): array => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'department' => $u->department?->name, 'assigned' => $assigned->has($u->id)])
            ->all());
    }

    public function saveStepUsers(Request $request, string $rule): JsonResponse
    {
        $this->allow('approval.type.manage');

        $step = ApprovalRule::query()->whereKey($rule)->firstOrFail();
        $company = $this->caller()->companyId;
        $data = $request->validate([
            'user_ids' => ['present', 'array'],
            'user_ids.*' => ['string', Rule::exists('company_users', 'user_id')->where('company_id', $company)->where('status', 'ACTIVE')],
        ]);

        DB::transaction(function () use ($step, $data, $company): void {
            ApprovalRuleUser::query()->where('rule_id', $step->id)->whereNotIn('user_id', $data['user_ids'])->delete();
            foreach (array_unique($data['user_ids']) as $userId) {
                ApprovalRuleUser::query()->firstOrCreate(['rule_id' => $step->id, 'user_id' => $userId], ['company_id' => $company]);
            }
        });

        return $this->stepUsers($rule);
    }

    /**
     * @return array<string, mixed>
     */
    private function workflowRules(?ApprovalWorkflow $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = $this->caller()->companyId;

        return [
            'type_code' => ['nullable', 'string', 'max:16', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/', Rule::unique('approval_workflows', 'type_code')->where('company_id', $company)->ignore($row?->id)],
            'name' => [$required, 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:32'],
            'document_type' => [$required, 'string', 'max:255'],
            'entity_type' => [$required, Rule::in([...ApprovalWorkflow::ENTITY_TYPES, 'MANUAL_REQUEST'])],
            'workflow_type' => ['nullable', Rule::in(self::WORKFLOW_TYPES)],
            'page_url' => ['nullable', 'string', 'max:255', 'regex:/^\/[A-Za-z0-9\/_\-?=&.]*$/'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function workflowRow(ApprovalWorkflow $w): array
    {
        return [
            'id' => $w->id, 'type_code' => $w->type_code, 'name' => $w->name, 'short_name' => $w->short_name,
            'document_type' => $w->document_type, 'entity_type' => $w->entity_type, 'workflow_type' => $w->workflow_type,
            'page_url' => $w->page_url, 'active' => $w->active,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function stepRows(ApprovalWorkflow $w): array
    {
        $counts = ApprovalRuleUser::query()->whereIn('rule_id', ApprovalRule::query()->where('workflow_id', $w->id)->pluck('id'))
            ->selectRaw('rule_id, count(*) as n')->groupBy('rule_id')->pluck('n', 'rule_id');

        return ApprovalRule::query()->with('role')->where('workflow_id', $w->id)->orderBy('sequence')->get()
            ->map(fn (ApprovalRule $r): array => [
                'id' => $r->id, 'sequence' => $r->sequence, 'name' => $r->name, 'task_message' => $r->task_message,
                'role_id' => $r->role_id, 'role' => $r->role?->description ?: $r->role?->name, 'role_flag' => $r->role_flag, 'next_action' => $r->next_action,
                'assigned' => (int) ($counts[$r->id] ?? 0), 'level' => $r->level,
                ...$this->bandOf($r),
                'requires_signature' => $r->requires_signature, 'can_repeat' => $r->can_repeat, 'can_cancel' => $r->can_cancel,
                'can_resend' => $r->can_resend, 'active' => $r->active,
            ])->all();
    }

    /**
     * @return array{min_cost: ?float, max_cost: ?float}
     */
    private function bandOf(ApprovalRule $r): array
    {
        $c = $r->condition_json ?? [];

        return ['min_cost' => isset($c['min_cost']) ? (float) $c['min_cost'] : null, 'max_cost' => isset($c['max_cost']) ? (float) $c['max_cost'] : null];
    }

    /**
     * What a waiting request depends on: which steps, in what order, at what level and band.
     *
     * @param  array<string, mixed>  $step
     * @return array<string, mixed>
     */
    private function shapeRow(array $step): array
    {
        return [
            'id' => $step['id'] ?? null, 'sequence' => (int) $step['sequence'], 'level' => (int) $step['level'],
            'active' => (bool) ($step['active'] ?? true),
            'min' => isset($step['min_cost']) && $step['min_cost'] !== '' ? (float) $step['min_cost'] : null,
            'max' => isset($step['max_cost']) && $step['max_cost'] !== '' ? (float) $step['max_cost'] : null,
            'role' => $step['role_id'] ?? null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function shapeOf(array $rows): string
    {
        usort($rows, fn ($a, $b) => [$a['sequence'], (string) $a['id']] <=> [$b['sequence'], (string) $b['id']]);

        return json_encode($rows, JSON_THROW_ON_ERROR);
    }

    private function pendingOn(ApprovalWorkflow $w): int
    {
        return ApprovalRequest::query()->where('workflow_id', $w->id)->where('status', 'PENDING')->count();
    }

    /** AT-001, AT-002 … after the highest one in use. */
    private function nextCode(): string
    {
        $max = ApprovalWorkflow::query()->where('type_code', 'like', 'AT-%')->pluck('type_code')
            ->map(fn (string $c): int => (int) substr($c, 3))->max() ?? 0;

        return sprintf('AT-%03d', $max + 1);
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'roles' => Role::query()->availableTo($this->caller()->companyId)->orderBy('name')->get(['id', 'name', 'description'])->map(fn (Role $r): array => ['id' => $r->id, 'name' => $r->description ?: $r->name])->all(),
            // Action Assignment's picker: "AT-001 › 4. Receive at Floor".
            'steps' => ApprovalRule::query()->with('workflow')->get()
                ->sortBy(fn (ApprovalRule $r): string => sprintf('%s-%05d', $r->workflow?->type_code ?? $r->workflow?->name, $r->sequence))
                ->map(fn (ApprovalRule $r): array => ['value' => $r->id, 'label' => sprintf('%s › %d. %s', $r->workflow?->type_code ?: $r->workflow?->name, $r->sequence, $r->name ?: '—')])
                ->values()->all(),
            'entity_types' => [...ApprovalWorkflow::ENTITY_TYPES, 'MANUAL_REQUEST'],
            'workflow_types' => self::WORKFLOW_TYPES,
        ];
    }
}
