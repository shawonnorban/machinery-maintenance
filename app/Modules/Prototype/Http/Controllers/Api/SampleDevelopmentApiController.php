<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Http\Controllers\Api;

use App\Modules\Prototype\Models\Sample;
use App\Modules\Prototype\Models\SampleDevelopment;
use App\Modules\Prototype\Models\SampleDevelopmentTask;
use App\Modules\Prototype\Models\SampleRequisition;
use App\Modules\Prototype\Models\SampleRevision;
use App\Modules\Prototype\Support\Prototype;
use App\Modules\Prototype\Support\SampleCards;
use App\Modules\Prototype\Support\SampleLedger;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Prototype Desk → Sample Development (AnnotechERP 07/03): pattern to
 * finishing for one version of an approved, open sample (DEV-2026-00001),
 * who makes it, and its fourteen-point checklist. Progress is completed
 * points over applicable ones (Not Required ones do not count); when every
 * applicable point is done, or the status is set to Completed, the
 * development is closed and locked, the actual completion defaulting to
 * today. Starting one puts the sample, its requisition and any revision of
 * that version in progress; completing it completes them.
 */
class SampleDevelopmentApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('prototype.development.view');
        $done = SampleDevelopment::query()->get(['sample_id', 'version'])->map(fn ($d) => $d->sample_id.':'.$d->version)->all();
        // An approved, open sample whose current version has no development yet.
        $samples = Sample::query()->where('state', '!=', 'CANCELLED')->orderByDesc('sample_no')->get()->reject(fn (Sample $s) => in_array($s->id.':'.$s->version, $done, true));

        return ApiResponse::ok(['samples' => SampleCards::options($samples), 'users' => Prototype::users(), 'statuses' => SampleDevelopment::STATUSES, 'tasks' => SampleDevelopment::TASKS]);
    }

    public function index(): JsonResponse
    {
        $this->allow('prototype.development.view');

        return ApiResponse::ok($this->present(SampleDevelopment::query()->orderByDesc('dev_date')->orderByDesc('dev_no')->get()));
    }

    public function show(string $development): JsonResponse
    {
        $this->allow('prototype.development.view');

        return ApiResponse::ok($this->present(collect([$this->find($development)]), true)[0]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('prototype.development.manage');
        $data = $this->validated($request, null);
        $sample = Sample::query()->findOrFail($data['sample_id']);
        if (! $sample->isOpen()) {
            throw ValidationException::withMessages(['sample_id' => __('prototype.development.sample_closed')]);
        }
        if (SampleDevelopment::query()->where('sample_id', $sample->id)->where('version', $sample->version)->exists()) {
            throw ValidationException::withMessages(['sample_id' => __('prototype.development.one_per_version', ['sample' => $sample->sample_no, 'version' => $sample->version])]);
        }
        $row = DB::transaction(function () use ($data, $sample): SampleDevelopment {
            $row = SampleDevelopment::query()->create(collect($data)->except('tasks')->all() + [
                'dev_no' => Prototype::nextNumber('DEV', 'proto_developments', 'dev_no', $data['dev_date']), 'version' => $sample->version,
            ]);
            foreach (SampleDevelopment::TASKS as $i => $task) {
                SampleDevelopmentTask::query()->create(['development_id' => $row->id, 'task' => $task, 'sort_order' => $i + 1]);
            }
            SampleLedger::log($sample, 'development_started', ['dev' => $row->dev_no, 'version' => $sample->version], $row->dev_no);
            SampleLedger::resolve('SAMPLE_ASSIGNED', $sample->sample_no);
            $this->apply($row, $data['tasks'] ?? []);

            return $row;
        });

        return ApiResponse::created($this->present(collect([$row->fresh()]), true)[0]);
    }

    public function update(Request $request, string $development): JsonResponse
    {
        $this->allow('prototype.development.manage');
        $row = $this->find($development);
        if ($row->isLocked()) {
            throw ValidationException::withMessages(['status' => __('prototype.development.locked', ['status' => __('prototype.dev_status.'.$row->status)])]);
        }
        $data = $this->validated($request, $row);
        DB::transaction(function () use ($row, $data): void {
            $row->update(collect($data)->except(['tasks', 'sample_id'])->all());
            $this->apply($row, $data['tasks'] ?? []);
        });

        return ApiResponse::ok($this->present(collect([$row->fresh()]), true)[0]);
    }

    /** The checklist as sent, progress worked out again, and a completed development closed with the sample it made. */
    private function apply(SampleDevelopment $row, array $tasks): void
    {
        $byTask = collect($tasks)->keyBy('task');
        foreach (SampleDevelopmentTask::query()->where('development_id', $row->id)->get() as $task) {
            if (! $byTask->has($task->task)) {
                continue;
            }
            $sent = $byTask[$task->task];
            $status = $sent['status'] ?? null;
            $task->update([
                'status' => $status, 'remarks' => $sent['remarks'] ?? null,
                'completed_at' => $status === 'COMPLETED' ? ($task->completed_at ?? now()) : null,
            ]);
        }
        $all = SampleDevelopmentTask::query()->where('development_id', $row->id)->get();
        $applicable = $all->where('status', '!=', 'NOT_REQUIRED');
        $done = $applicable->where('status', 'COMPLETED')->count();
        $progress = $applicable->count() ? round($done / $applicable->count() * 100, 1) : 0.0;
        $complete = $row->status === 'COMPLETED' || ($applicable->count() > 0 && $done === $applicable->count());
        $row->update([
            'progress' => $complete ? 100 : $progress,
            'status' => $complete ? 'COMPLETED' : ($row->status === 'PENDING' && $done > 0 ? 'IN_PROGRESS' : $row->status),
            'actual_completion' => $complete ? ($row->actual_completion ?? CarbonImmutable::today()) : $row->actual_completion,
        ]);

        // The sample, its requisition and the revision of this version follow the development.
        $sample = Sample::query()->findOrFail($row->sample_id);
        $revision = SampleRevision::query()->where('sample_id', $sample->id)->where('new_version', $row->version)->first();
        if ($row->status === 'CANCELLED') {
            return;
        }
        // Finished, the sample waits in Quality for its check; until then it is in development (or its revision).
        $sample->update($complete ? ['phase' => 'QUALITY', 'state' => 'PENDING'] : ['phase' => $revision ? 'REVISION' : 'DEVELOPMENT', 'state' => 'IN_PROGRESS']);
        SampleRequisition::query()->whereKey($sample->requisition_id)->whereNotIn('status', ['CANCELLED'])->update(['status' => $complete ? 'COMPLETED' : 'IN_DEVELOPMENT']);
        $revision?->update(['status' => $complete ? 'COMPLETED' : 'IN_PROGRESS']);
        // Finished (again, after a rework): the timeline says so once, and Quality is asked to check it.
        if ($complete && SampleLedger::lastAction($row->dev_no) !== 'development_completed') {
            SampleLedger::log($sample, 'development_completed', ['dev' => $row->dev_no, 'version' => $row->version], $row->dev_no);
            SampleLedger::raise('QC_REQUIRED', 'INFO', $sample, 'QC', "{$sample->sample_no}-V{$row->version}", ['sample' => $sample->sample_no, 'version' => $row->version]);
        }
    }

    private function find(string $development): SampleDevelopment
    {
        return SampleDevelopment::query()->where('dev_no', $development)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?SampleDevelopment $row): array
    {
        $company = app(TenantContext::class)->companyId();
        $user = ['nullable', Rule::exists('company_users', 'user_id')->where('company_id', $company)];
        $data = $request->validate([
            'sample_id' => [$row ? 'nullable' : 'required', Rule::exists('proto_samples', 'id')->where('company_id', $company)],
            'status' => ['nullable', Rule::in(SampleDevelopment::STATUSES)],
            'dev_date' => ['required', 'date'],
            'start_date' => ['nullable', 'date'],
            'expected_completion' => ['nullable', 'date'],
            'actual_completion' => ['nullable', 'date'],
            'pattern_master' => $user, 'sample_maker' => $user, 'technician' => $user, 'supervisor' => $user,
            'delay_reason' => ['nullable', 'string', 'max:500'],
            'remarks' => ['nullable', 'string', 'max:4000'],
            'tasks' => ['nullable', 'array'],
            'tasks.*.task' => ['required', Rule::in(SampleDevelopment::TASKS)],
            'tasks.*.status' => ['nullable', Rule::in(SampleDevelopment::TASK_STATUSES)],
            'tasks.*.remarks' => ['nullable', 'string', 'max:500'],
        ], ['sample_id.required' => __('prototype.development.sample_required'), 'dev_date.required' => __('prototype.development.date_required')]);
        foreach ([['start_date', 'dev_date'], ['expected_completion', 'start_date'], ['actual_completion', 'start_date']] as [$later, $earlier]) {
            if (! empty($data[$later]) && ! empty($data[$earlier]) && CarbonImmutable::parse($data[$later])->lt(CarbonImmutable::parse($data[$earlier]))) {
                throw ValidationException::withMessages([$later => __('prototype.development.date_order', ['field' => __('prototype.development.field.'.$later), 'other' => __('prototype.development.field.'.$earlier)])]);
            }
        }
        $data['status'] = $data['status'] ?? ($row?->status ?? 'PENDING');

        return $data;
    }

    /** @return list<array<string, mixed>> */
    private function present($rows, bool $detail = false): array
    {
        $cards = SampleCards::of(Sample::query()->whereIn('id', $rows->pluck('sample_id'))->get());
        $tasks = SampleDevelopmentTask::query()->whereIn('development_id', $rows->pluck('id'))->orderBy('sort_order')->get()->groupBy('development_id');
        $names = Prototype::names($rows->flatMap(fn ($r) => [$r->pattern_master, $r->sample_maker, $r->technician, $r->supervisor])->all());
        $today = CarbonImmutable::today();

        return $rows->map(function (SampleDevelopment $r) use ($cards, $tasks, $names, $today, $detail): array {
            $list = $tasks[$r->id] ?? collect();
            $applicable = $list->where('status', '!=', 'NOT_REQUIRED');
            $out = [
                'id' => $r->id, 'dev_no' => $r->dev_no, 'dev_date' => $r->dev_date->toDateString(), 'version' => $r->version, 'status' => $r->status, 'progress' => $r->progress,
                'start_date' => $r->start_date?->toDateString(), 'expected_completion' => $r->expected_completion?->toDateString(), 'actual_completion' => $r->actual_completion?->toDateString(),
                'overdue' => $r->expected_completion !== null && $r->expected_completion->lt($today) && ! $r->isLocked(),
                'tasks_done' => $applicable->where('status', 'COMPLETED')->count(), 'tasks_total' => $applicable->count(), 'locked' => $r->isLocked(),
                'pattern_master' => $r->pattern_master, 'sample_maker' => $r->sample_maker, 'technician' => $r->technician, 'supervisor' => $r->supervisor,
                'pattern_master_name' => $names[$r->pattern_master] ?? null, 'sample_maker_name' => $names[$r->sample_maker] ?? null,
                'delay_reason' => $r->delay_reason, 'remarks' => $r->remarks,
            ] + ($cards[$r->sample_id] ?? []);
            if ($detail) {
                $out['tasks'] = $list->map(fn (SampleDevelopmentTask $t): array => ['task' => $t->task, 'status' => $t->status, 'completed_at' => $t->completed_at?->toIso8601String(), 'remarks' => $t->remarks])->values()->all();
            }

            return $out;
        })->values()->all();
    }
}
