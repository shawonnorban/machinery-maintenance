<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api;

use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\TnaEventInstance;
use App\Modules\Merchandise\Models\TnaEventUser;
use App\Modules\Merchandise\Models\TnaTask;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Access Control → TNA Task Status Setup (AnnotechERP 02/04), as Brickland
 * keeps it. The calendar events Merchandise's TNA templates are made of —
 * each with its message, order, parent, section, owner, the status a fresh
 * instance starts in, offset from shipment, and whether it repeats per colour
 * or delivery and needs buyer feedback. An event is raised against a style
 * (an instance with its owner and plan date) and updated as it moves —
 * completing one stamps today as its actual date. Mapped User List: who is
 * notified when an event is raised, beyond the owning desk.
 */
class TnaEventApiController extends ApiController
{
    /** Records: every event, in status order, with how often it has been raised. */
    public function index(): JsonResponse
    {
        $this->allow('merchandise.tna.view');
        $events = TnaTask::query()->orderBy('sort_order')->orderBy('code')->get();
        $raised = TnaEventInstance::query()->groupBy('tna_task_id')->select('tna_task_id', DB::raw('count(*) as n'))->pluck('n', 'tna_task_id');
        $sections = DB::table('departments')->whereIn('id', $events->pluck('section_id')->filter()->unique())->pluck('name', 'id');
        $users = User::query()->whereIn('id', $events->pluck('assign_to')->merge($events->pluck('execution_by'))->filter()->unique())->pluck('name', 'id');
        $names = $events->pluck('name', 'id');

        return ApiResponse::ok($events->map(fn (TnaTask $e): array => [
            'id' => $e->id, 'code' => $e->code, 'name' => $e->name, 'task_message' => $e->task_message, 'sort_order' => $e->sort_order,
            'parent_id' => $e->parent_id, 'parent' => $names[$e->parent_id] ?? null, 'section_id' => $e->section_id, 'section' => $sections[$e->section_id] ?? null,
            'assign_to' => $e->assign_to, 'assign_to_name' => $users[$e->assign_to] ?? null, 'execution_by' => $e->execution_by, 'execution_by_name' => $users[$e->execution_by] ?? null,
            'default_status' => $e->default_status, 'offset_days' => $e->offset_days, 'is_repeat' => $e->is_repeat, 'is_buyer_feedback' => $e->is_buyer_feedback,
            'raised' => (int) ($raised[$e->id] ?? 0), 'active' => $e->active,
        ])->values()->all());
    }

    /** What the event form picks from. */
    public function options(): JsonResponse
    {
        $this->allow('merchandise.tna.view');

        return ApiResponse::ok([
            'parents' => TnaTask::query()->orderBy('sort_order')->orderBy('code')->get()->map(fn (TnaTask $e) => ['value' => $e->id, 'label' => "{$e->name} ({$e->code})"])->all(),
            // A section is a department; the same name in two factories is one choice.
            'sections' => DB::table('departments')->where('company_id', app(TenantContext::class)->companyId())->where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name'])
                ->unique('name')->map(fn ($d) => ['value' => $d->id, 'label' => $d->name])->values()->all(),
            'users' => $this->companyUsers()->map(fn (User $u) => ['value' => $u->id, 'label' => $u->name.' ('.($u->username ?? $u->email).')'])->values()->all(),
            'statuses' => TnaTask::STATUSES,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('merchandise.tna.manage');
        $event = TnaTask::query()->create($this->validated($request, null));

        return ApiResponse::created(['id' => $event->id, 'code' => $event->code]);
    }

    public function update(Request $request, TnaTask $task): JsonResponse
    {
        $this->allow('merchandise.tna.manage');
        $task->update($this->validated($request, $task));

        return ApiResponse::ok(['id' => $task->id, 'code' => $task->code]);
    }

    /** One event's raised instances, newest plan first. */
    public function instances(TnaTask $task): JsonResponse
    {
        $this->allow('merchandise.tna.view');

        return ApiResponse::ok($this->instanceRows($task));
    }

    /**
     * Saves an event's instances as the screen holds them: new ones raised (the
     * event's starting status), statuses and dates changed; completing one
     * stamps today as its actual date.
     */
    public function saveInstances(Request $request, TnaTask $task): JsonResponse
    {
        $this->allow('merchandise.tna.manage');
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.id' => ['nullable', 'string'],
            'rows.*.reference' => ['required', 'string', 'max:120'],
            'rows.*.owner_id' => ['nullable', Rule::exists('company_users', 'user_id')->where('company_id', $company)],
            'rows.*.plan_date' => ['nullable', 'date'],
            'rows.*.status' => ['nullable', Rule::in(TnaTask::STATUSES)],
        ], ['rows.*.reference.required' => __('access.tna.v.reference')]);
        $existing = TnaEventInstance::query()->where('tna_task_id', $task->id)->get()->keyBy('id');
        DB::transaction(function () use ($data, $existing, $task): void {
            foreach ($data['rows'] as $row) {
                $status = $row['status'] ?? null;
                $current = isset($row['id']) ? $existing->get($row['id']) : null;
                $values = ['reference' => trim($row['reference']), 'owner_id' => $row['owner_id'] ?? null, 'plan_date' => $row['plan_date'] ?? null];
                if ($current) {
                    $status ??= $current->status;
                    $current->update($values + ['status' => $status, 'actual_date' => $status === 'COMPLETED' ? ($current->actual_date ?? now()->toDateString()) : null]);
                } else {
                    $status ??= $task->default_status ?? 'PENDING';
                    TnaEventInstance::query()->create($values + ['tna_task_id' => $task->id, 'status' => $status, 'actual_date' => $status === 'COMPLETED' ? now()->toDateString() : null]);
                }
            }
        });

        return ApiResponse::ok($this->instanceRows($task));
    }

    /** Mapped User List: every user of the company, those notified ticked. */
    public function users(TnaTask $task): JsonResponse
    {
        $this->allow('merchandise.tna.view');

        return ApiResponse::ok($this->userRows($task));
    }

    public function saveUsers(Request $request, TnaTask $task): JsonResponse
    {
        $this->allow('merchandise.tna.manage');
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate(['users' => ['present', 'array'], 'users.*' => [Rule::exists('company_users', 'user_id')->where('company_id', $company)]]);
        DB::transaction(function () use ($task, $data): void {
            TnaEventUser::query()->where('tna_task_id', $task->id)->delete();
            foreach (array_unique($data['users']) as $user) {
                TnaEventUser::query()->create(['tna_task_id' => $task->id, 'user_id' => $user]);
            }
        });

        return ApiResponse::ok($this->userRows($task));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?TnaTask $task): array
    {
        $company = app(TenantContext::class)->companyId();
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:16', Rule::unique('tna_tasks', 'code')->where('company_id', $company)->ignore($task?->id)],
            'name' => ['required', 'string', 'max:255'],
            'task_message' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'parent_id' => ['nullable', Rule::exists('tna_tasks', 'id')->where('company_id', $company), ...($task ? [Rule::notIn([$task->id])] : [])],
            'section_id' => ['nullable', Rule::exists('departments', 'id')->where('company_id', $company)],
            'assign_to' => ['nullable', Rule::exists('company_users', 'user_id')->where('company_id', $company)],
            'execution_by' => ['nullable', Rule::exists('company_users', 'user_id')->where('company_id', $company)],
            'default_status' => ['nullable', Rule::in(TnaTask::STATUSES)],
            'offset_days' => ['nullable', 'integer', 'between:-999,999'],
            'is_repeat' => ['sometimes', 'boolean'], 'is_buyer_feedback' => ['sometimes', 'boolean'], 'active' => ['sometimes', 'boolean'],
        ], [
            'code.required' => __('access.tna.v.code'), 'code.unique' => __('access.tna.v.code_taken'), 'name.required' => __('access.tna.v.name'),
            'sort_order.required' => __('access.tna.v.order'), 'parent_id.not_in' => __('access.tna.v.parent_self'),
        ]);

        return $data + ['is_repeat' => false, 'is_buyer_feedback' => false, 'active' => $task?->active ?? true];
    }

    /** @return list<array<string, mixed>> */
    private function instanceRows(TnaTask $task): array
    {
        $rows = TnaEventInstance::query()->where('tna_task_id', $task->id)->orderByDesc('plan_date')->orderByDesc('created_at')->get();
        $owners = User::query()->whereIn('id', $rows->pluck('owner_id')->filter()->unique())->pluck('name', 'id');

        return $rows->map(fn (TnaEventInstance $i): array => [
            'id' => $i->id, 'reference' => $i->reference, 'owner_id' => $i->owner_id, 'owner' => $owners[$i->owner_id] ?? null,
            'plan_date' => $i->plan_date?->toDateString(), 'actual_date' => $i->actual_date?->toDateString(), 'status' => $i->status,
        ])->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function userRows(TnaTask $task): array
    {
        $mapped = TnaEventUser::query()->where('tna_task_id', $task->id)->pluck('user_id')->flip();
        $users = $this->companyUsers();
        $depts = DB::table('departments')->whereIn('id', $users->pluck('department_id')->filter()->unique())->pluck('name', 'id');

        return $users->map(fn (User $u): array => [
            'user_id' => $u->id, 'name' => $u->name, 'login' => $u->username ?? $u->email, 'department' => $depts[$u->department_id] ?? null, 'selected' => isset($mapped[$u->id]),
        ])->values()->all();
    }

    /** @return Collection<int, User> */
    private function companyUsers()
    {
        $ids = CompanyUser::query()->where('company_id', app(TenantContext::class)->companyId())->pluck('user_id');

        return User::query()->whereIn('id', $ids)->orderBy('name')->get(['id', 'name', 'username', 'email', 'department_id']);
    }
}
