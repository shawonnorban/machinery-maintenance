<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\TnaTask;
use App\Modules\Merchandise\Models\TnaTaskGroup;
use App\Modules\Merchandise\Models\TnaTaskRight;
use App\Modules\Merchandise\Models\TnaTemplate;
use App\Modules\Merchandise\Models\TnaTemplateTask;
use App\Modules\Merchandise\Support\MerchandiseDefaults;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * T&A building blocks (AnnotechERP 06/01): a template's milestone calendar —
 * offsets counted back from the shipment date — and copying a template; and
 * TNA Task Rights, where seeing a task is the floor and create, update and
 * delete are granted on top.
 */
class TnaApiController extends ApiController
{
    public function templateTasks(string $template): JsonResponse
    {
        $this->allow('merchandise.master.view');
        $row = TnaTemplate::query()->whereKey($template)->firstOrFail();

        return ApiResponse::ok($this->calendar($row));
    }

    /** The whole calendar at once, as the grid holds it. */
    public function saveTemplateTasks(Request $request, string $template): JsonResponse
    {
        $this->allow('merchandise.master.manage');
        $row = TnaTemplate::query()->whereKey($template)->firstOrFail();
        $company = $this->caller()->companyId;
        $data = $request->validate([
            'tasks' => ['present', 'array', 'max:200'],
            'tasks.*.tna_task_id' => ['required', 'distinct', Rule::exists('tna_tasks', 'id')->where('company_id', $company)],
            // A milestone cannot fall before the template's longest lead time.
            'tasks.*.offset_days' => ['required', 'integer', 'min:-'.$row->lead_to, 'max:60'],
            'tasks.*.duration_days' => ['required', 'integer', 'min:1', 'max:365'],
            'tasks.*.sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'tasks.*.active' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($row, $data, $company): void {
            $keep = array_column($data['tasks'], 'tna_task_id');
            TnaTemplateTask::query()->where('tna_template_id', $row->id)->whereNotIn('tna_task_id', $keep)->delete();
            foreach ($data['tasks'] as $task) {
                TnaTemplateTask::query()->updateOrCreate(
                    ['tna_template_id' => $row->id, 'tna_task_id' => $task['tna_task_id']],
                    ['company_id' => $company, 'offset_days' => $task['offset_days'], 'duration_days' => $task['duration_days'], 'sort_order' => $task['sort_order'], 'active' => (bool) ($task['active'] ?? true)],
                );
            }
        });

        return ApiResponse::ok($this->calendar($row));
    }

    /** A copy under a new code, calendar and all. */
    public function copyTemplate(Request $request, string $template): JsonResponse
    {
        $this->allow('merchandise.master.manage');
        $row = TnaTemplate::query()->whereKey($template)->firstOrFail();
        $data = $request->validate(['code' => ['nullable', 'string', 'max:16']]);
        $code = strtoupper($data['code'] ?? '') ?: $this->copyCode($row->code);
        if (TnaTemplate::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages(['code' => __('merchandise.master.code_taken')]);
        }

        $copy = DB::transaction(function () use ($row, $code): TnaTemplate {
            $copy = $row->replicate();
            $copy->code = $code;
            $copy->save();
            foreach (TnaTemplateTask::query()->where('tna_template_id', $row->id)->get() as $task) {
                $clone = $task->replicate();
                $clone->tna_template_id = $copy->id;
                $clone->save();
            }

            return $copy;
        });

        return ApiResponse::created(['id' => $copy->id, 'code' => $copy->code]);
    }

    /** Records: every grant, user by user. */
    public function rights(): JsonResponse
    {
        $this->allow('merchandise.master.view');
        $tasks = TnaTask::query()->get()->keyBy('id');
        $groups = TnaTaskGroup::query()->pluck('name', 'id');
        $users = User::query()->whereIn('id', TnaTaskRight::query()->pluck('user_id')->unique())->pluck('name', 'id');

        return ApiResponse::ok(TnaTaskRight::query()->get()->map(function (TnaTaskRight $r) use ($tasks, $groups, $users): array {
            $task = $tasks->get($r->tna_task_id);

            return [
                'id' => $r->id, 'user' => $users[$r->user_id] ?? null, 'task' => $task?->name, 'group' => $task?->tna_task_group_id ? ($groups[$task->tna_task_group_id] ?? null) : null,
                'crud' => ($r->can_create ? 'C' : '·').'R'.($r->can_update ? 'U' : '·').($r->can_delete ? 'D' : '·'),
            ];
        })->sortBy(['user', 'task'])->values()->all());
    }

    /** TNA Task Rights → Records: Brickland's Remove on one user's right to one task. */
    public function removeRight(string $right): JsonResponse
    {
        $this->allow('merchandise.master.manage');
        TnaTaskRight::query()->findOrFail($right)->delete();

        return ApiResponse::noContent();
    }

    public function userRights(string $user): JsonResponse
    {
        $this->allow('merchandise.master.view');

        return ApiResponse::ok($this->rightsOf($this->member($user)));
    }

    /** A user's tasks at once: a ticked task is visible; create, update and delete come on top. */
    public function saveUserRights(Request $request, string $user): JsonResponse
    {
        $this->allow('merchandise.master.manage');
        $member = $this->member($user);
        $company = $this->caller()->companyId;
        $data = $request->validate([
            'rights' => ['present', 'array'],
            'rights.*.tna_task_id' => ['required', 'distinct', Rule::exists('tna_tasks', 'id')->where('company_id', $company)],
            'rights.*.can_create' => ['sometimes', 'boolean'],
            'rights.*.can_update' => ['sometimes', 'boolean'],
            'rights.*.can_delete' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($member, $data, $company): void {
            TnaTaskRight::query()->where('user_id', $member->id)->whereNotIn('tna_task_id', array_column($data['rights'], 'tna_task_id'))->delete();
            foreach ($data['rights'] as $right) {
                TnaTaskRight::query()->updateOrCreate(
                    ['user_id' => $member->id, 'tna_task_id' => $right['tna_task_id']],
                    ['company_id' => $company, 'can_create' => (bool) ($right['can_create'] ?? false), 'can_update' => (bool) ($right['can_update'] ?? false), 'can_delete' => (bool) ($right['can_delete'] ?? false)],
                );
            }
        });

        return ApiResponse::ok($this->rightsOf($member));
    }

    /** Who the rights picker offers: this company's active members. */
    public function users(): JsonResponse
    {
        $this->allow('merchandise.master.view');
        $members = CompanyUser::query()->where('company_id', $this->caller()->companyId)->where('status', 'ACTIVE')->pluck('user_id');

        return ApiResponse::ok(User::query()->whereIn('id', $members)->orderBy('name')->get(['id', 'name', 'email'])
            ->map(fn (User $u): array => ['value' => $u->id, 'label' => "{$u->name} ({$u->email})"])->all());
    }

    private function member(string $user): User
    {
        $isMember = CompanyUser::query()->where('company_id', $this->caller()->companyId)->where('user_id', $user)->where('status', 'ACTIVE')->exists();
        abort_unless($isMember, 404);

        return User::query()->findOrFail($user);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rightsOf(User $user): array
    {
        MerchandiseDefaults::ensureTna($this->caller()->companyId);
        $granted = TnaTaskRight::query()->where('user_id', $user->id)->get()->keyBy('tna_task_id');
        $groups = TnaTaskGroup::query()->pluck('name', 'id');

        return TnaTask::query()->where('active', true)->orderBy('sort_order')->get()->map(function (TnaTask $t) use ($granted, $groups): array {
            $g = $granted->get($t->id);

            return ['tna_task_id' => $t->id, 'name' => $t->name, 'group' => $t->tna_task_group_id ? ($groups[$t->tna_task_group_id] ?? null) : null, 'sort_order' => $t->sort_order,
                'granted' => $g !== null, 'can_create' => (bool) $g?->can_create, 'can_update' => (bool) $g?->can_update, 'can_delete' => (bool) $g?->can_delete];
        })->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function calendar(TnaTemplate $template): array
    {
        MerchandiseDefaults::ensureTna($this->caller()->companyId);
        $tasks = TnaTask::query()->get()->keyBy('id');
        $groups = TnaTaskGroup::query()->pluck('name', 'id');
        $rows = TnaTemplateTask::query()->where('tna_template_id', $template->id)->orderBy('sort_order')->get()->map(function (TnaTemplateTask $tt) use ($tasks, $groups): array {
            $task = $tasks->get($tt->tna_task_id);

            return ['tna_task_id' => $tt->tna_task_id, 'name' => $task?->name, 'group' => $task?->tna_task_group_id ? ($groups[$task->tna_task_group_id] ?? null) : null,
                'offset_days' => $tt->offset_days, 'duration_days' => $tt->duration_days, 'sort_order' => $tt->sort_order, 'active' => $tt->active];
        })->all();

        return [
            'template' => ['id' => $template->id, 'code' => $template->code, 'lead_from' => $template->lead_from, 'lead_to' => $template->lead_to, 'order_type' => $template->order_type],
            'tasks' => $rows,
            'available' => $tasks->where('active', true)->sortBy('sort_order')->map(fn (TnaTask $t): array => ['value' => $t->id, 'label' => $t->name])->values()->all(),
        ];
    }

    private function copyCode(string $code): string
    {
        $n = 1;
        do {
            $candidate = mb_substr($code, 0, 12).'-C'.$n++;
        } while (TnaTemplate::query()->where('code', $candidate)->exists());

        return $candidate;
    }
}
