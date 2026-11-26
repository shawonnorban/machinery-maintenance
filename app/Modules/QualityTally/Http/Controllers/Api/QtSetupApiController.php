<?php

declare(strict_types=1);

namespace App\Modules\QualityTally\Http\Controllers\Api;

use App\Modules\HrPayroll\Models\HrEmployee;
use App\Modules\QualityTally\Http\Controllers\Concerns\GuardsSections;
use App\Modules\QualityTally\Models\QtDefectType;
use App\Modules\QualityTally\Models\QtEntry;
use App\Modules\QualityTally\Models\QtEntryDefect;
use App\Modules\QualityTally\Models\QtInspector;
use App\Modules\QualityTally\Models\QtInspectorLine;
use App\Modules\QualityTally\Models\QtJob;
use App\Modules\QualityTally\Models\QtLineAttendance;
use App\Modules\QualityTally\Models\QtLineTarget;
use App\Modules\QualityTally\Support\QtBook;
use App\Modules\QualityTally\Support\QtFlow;
use App\Modules\QualityTally\Support\QtPhones;
use App\Modules\QualityTally\Support\QtPins;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Quality Tally (AnnotechERP 36): what its screens pick from, and its inputs —
 * Defect Types (per section), Jobs, Line Target and QC Assignment.
 */
class QtSetupApiController extends ApiController
{
    use GuardsSections;

    private const VIEW = 'qtally.view';

    /* ── Lookups ────────────────────────────────────────────────────── */

    public function lookups(): JsonResponse
    {
        $this->allowAnySection();
        $c = $this->caller()->companyId;
        $mine = QtFlow::sections($this->caller());
        $lines = [];
        foreach ($mine as $section) {
            $lines[$section] = QtBook::lines($c, $section)->where('active', true)->map(fn ($l) => ['value' => $l->id, 'label' => QtBook::label($l), 'code' => $l->code])->values()->all();
        }

        return ApiResponse::ok([
            'today' => QtBook::today(),
            'sections' => $mine,
            // The sections whose targets and input this caller manages (each section has its own manager).
            'managed' => QtFlow::managedSections($this->caller()),
            'lines' => $lines,
            'jobs' => QtJob::query()->where('status', 'OPEN')->orderBy('job_no')->get()->map(fn (QtJob $j) => ['value' => $j->id, 'label' => $this->jobLabel($j), 'job_no' => $j->job_no, 'smv' => $j->smv === null ? null : (float) $j->smv])->all(),
            'all_jobs' => QtJob::query()->orderBy('job_no')->get()->map(fn (QtJob $j) => ['value' => $j->id, 'label' => $this->jobLabel($j)])->all(),
        ]);
    }

    /* ── Defect Types ───────────────────────────────────────────────── */

    public function defectTypes(): JsonResponse
    {
        $this->allowEither(self::VIEW, 'qtally.defect.manage');
        $used = QtEntryDefect::query()->select('defect_type_id', DB::raw('count(*) as n'))->groupBy('defect_type_id')->pluck('n', 'defect_type_id');

        return ApiResponse::ok(QtDefectType::query()->orderBy('section')->orderBy('sort_order')->orderBy('code')->get()->map(fn (QtDefectType $d) => [
            'id' => $d->id, 'section' => $d->section, 'code' => $d->code, 'name' => $d->name, 'name_bn' => $d->name_bn,
            'sort_order' => $d->sort_order, 'active' => $d->active, 'used' => (int) ($used[$d->id] ?? 0),
        ])->all());
    }

    public function saveDefectType(Request $request, ?string $id = null): JsonResponse
    {
        $this->allow('qtally.defect.manage');
        $row = $id ? QtDefectType::query()->findOrFail($id) : null;
        $section = $request->input('section', $row?->section);
        $d = $request->validate([
            'section' => ['required', Rule::in(QtBook::SECTIONS)],
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('qt_defect_types', 'code')->where('company_id', $this->caller()->companyId)->where('section', $section)->ignore($row?->id)],
            'name' => ['required', 'string', 'max:120'],
            'name_bn' => ['nullable', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'active' => ['sometimes', 'boolean'],
        ], [], __('qtally.attrs'));
        $d['code'] = strtoupper($d['code']);
        // A defect already tagged keeps its section: moving it would move old taps' faults with it.
        if ($row && $row->section !== $d['section'] && QtEntryDefect::query()->where('defect_type_id', $row->id)->exists()) {
            throw ValidationException::withMessages(['section' => __('qtally.v.defect_section_used')]);
        }
        $values = $d + ['sort_order' => $d['sort_order'] ?? 0, 'active' => $d['active'] ?? true];
        $saved = $row ? tap($row)->update($values) : QtDefectType::query()->create($values);

        return $id ? ApiResponse::ok(['id' => $saved->id]) : ApiResponse::created(['id' => $saved->id]);
    }

    public function deleteDefectType(string $id): JsonResponse
    {
        $this->allow('qtally.defect.manage');
        $row = QtDefectType::query()->findOrFail($id);
        if (QtEntryDefect::query()->where('defect_type_id', $row->id)->exists()) {
            throw ValidationException::withMessages(['id' => __('qtally.v.defect_used')]);
        }
        $row->delete();

        return ApiResponse::ok(['id' => $id]);
    }

    /** Adds the standard list of one section (or all), skipping codes already there. */
    public function standardDefects(Request $request): JsonResponse
    {
        $this->allow('qtally.defect.manage');
        $d = $request->validate(['section' => ['nullable', Rule::in(QtBook::SECTIONS)]]);
        $added = 0;
        foreach (QtBook::standardDefects() as $section => $list) {
            if (($d['section'] ?? null) && $d['section'] !== $section) {
                continue;
            }
            $have = QtDefectType::query()->where('section', $section)->pluck('code')->all();
            foreach ($list as $i => [$code, $name, $bn]) {
                if (! in_array($code, $have, true)) {
                    QtDefectType::query()->create(['section' => $section, 'code' => $code, 'name' => $name, 'name_bn' => $bn, 'sort_order' => ($i + 1) * 10, 'active' => true]);
                    $added++;
                }
            }
        }

        return ApiResponse::ok(['added' => $added]);
    }

    /* ── Jobs ───────────────────────────────────────────────────────── */

    public function jobs(Request $request): JsonResponse
    {
        $this->allowEither(self::VIEW, 'qtally.job.manage');
        $q = $request->validate(['status' => ['nullable', Rule::in(['OPEN', 'CLOSED'])], 'search' => ['nullable', 'string', 'max:80']]);
        $rows = QtJob::query()
            ->when($q['status'] ?? null, fn ($w, $s) => $w->where('status', $s))
            ->when($q['search'] ?? null, fn ($w, $s) => $w->where(fn ($x) => $x->where('job_no', 'ilike', "%{$s}%")->orWhere('buyer_name', 'ilike', "%{$s}%")->orWhere('style', 'ilike', "%{$s}%")->orWhere('po_no', 'ilike', "%{$s}%")))
            ->orderByDesc('created_at')->get();
        // Pass so far, per section: the same garment passes cutting, sewing and quality, so the
        // sections are never added together.
        $counts = QtEntry::query()->whereIn('job_id', $rows->pluck('id'))->select('job_id', 'section', 'action', DB::raw('count(*) as n'))->groupBy('job_id', 'section', 'action')->get()->groupBy('job_id');
        $users = DB::table('users')->whereIn('id', $rows->pluck('updated_by')->merge($rows->pluck('created_by'))->filter()->unique())->pluck('name', 'id');

        return ApiResponse::ok($rows->map(function (QtJob $j) use ($counts, $users) {
            $mine = $counts[$j->id] ?? collect();
            $pass = [];
            foreach (QtBook::SECTIONS as $s) {
                $pass[$s] = QtBook::metrics($mine->where('section', $s)->pluck('n', 'action')->map(fn ($n) => (int) $n)->all(), 0)['pass'];
            }

            return $this->job($j) + [
                'pass' => $pass,
                'tallied' => $mine->isNotEmpty(),
                'updated_by' => $users[$j->updated_by ?? $j->created_by] ?? null, 'updated_at' => $j->updated_at?->toIso8601String(),
            ];
        })->all());
    }

    public function saveJob(Request $request, ?string $id = null): JsonResponse
    {
        $this->allow('qtally.job.manage');
        $row = $id ? QtJob::query()->findOrFail($id) : null;
        $request->merge(['po_no' => trim((string) $request->input('po_no', '')), 'color' => trim((string) $request->input('color', '')), 'size' => trim((string) $request->input('size', '')), 'job_no' => trim((string) $request->input('job_no', ''))]);
        $d = $request->validate([
            'job_no' => ['required', 'string', 'max:40',
                Rule::unique('qt_jobs', 'job_no')->where('company_id', $this->caller()->companyId)->where('po_no', $request->input('po_no'))->where('color', $request->input('color'))->where('size', $request->input('size'))->ignore($row?->id)],
            'buyer_name' => ['required', 'string', 'max:120'],
            'style' => ['required', 'string', 'max:120'],
            'po_no' => ['present', 'nullable', 'string', 'max:64'],
            'color' => ['present', 'nullable', 'string', 'max:64'],
            'size' => ['sometimes', 'nullable', 'string', 'max:64'],
            'due_date' => ['nullable', 'date'],
            'order_qty' => ['required', 'integer', 'min:1', 'max:99999999'],
            // Pieces each section had passed before this system: no section can have passed more than the one before.
            'opening_cut' => ['sometimes', 'integer', 'min:0', 'max:99999999'],
            'opening_sewing' => ['sometimes', 'integer', 'min:0', 'max:99999999'],
            'opening_quality' => ['sometimes', 'integer', 'min:0', 'max:99999999'],
            'smv' => ['nullable', 'numeric', 'gt:0', 'max:999'],
            'status' => ['sometimes', Rule::in(['OPEN', 'CLOSED'])],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ], ['job_no.unique' => __('qtally.v.job_unique')], __('qtally.attrs'));
        $opening = fn (string $k): int => (int) ($d[$k] ?? $row?->getAttribute($k) ?? 0);
        foreach ([['opening_sewing', 'opening_cut'], ['opening_quality', 'opening_sewing']] as [$k, $before]) {
            if ($opening($k) > $opening($before)) {
                throw ValidationException::withMessages([$k => __('qtally.v.opening_order')]);
            }
        }
        $me = $this->caller()->auditUserId();
        $values = ['po_no' => $d['po_no'] ?? '', 'color' => $d['color'] ?? '', 'size' => $d['size'] ?? '', 'status' => $d['status'] ?? ($row->status ?? 'OPEN'), 'updated_by' => $me] + $d;
        $saved = $row ? tap($row)->update($values) : QtJob::query()->create($values + ['created_by' => $me]);

        return $id ? ApiResponse::ok(['id' => $saved->id]) : ApiResponse::created(['id' => $saved->id]);
    }

    public function deleteJob(string $id): JsonResponse
    {
        $this->allow('qtally.job.manage');
        $row = QtJob::query()->findOrFail($id);
        if (QtEntry::query()->where('job_id', $row->id)->exists()) {
            throw ValidationException::withMessages(['id' => __('qtally.v.job_tallied')]);
        }
        QtLineTarget::query()->where('job_id', $row->id)->delete();
        $row->delete();

        return ApiResponse::ok(['id' => $id]);
    }

    /* ── Line Target ────────────────────────────────────────────────── */

    public function targets(Request $request): JsonResponse
    {
        $this->allowView($request->filled('section') ? (string) $request->input('section') : null);
        $q = $request->validate(['date' => ['nullable', 'date'], 'section' => ['nullable', Rule::in(QtBook::SECTIONS)]]);
        $c = $this->caller()->companyId;
        $date = CarbonImmutable::parse($q['date'] ?? QtBook::today())->toDateString();
        $lines = QtBook::allLines($c);
        $rows = QtLineTarget::query()->whereDate('target_date', $date)->when($q['section'] ?? null, fn ($w, $s) => $w->where('section', $s))->get();
        $jobs = QtJob::query()->whereIn('id', $rows->pluck('job_id'))->get()->keyBy('id');
        $done = $this->passBy($date, $rows);
        $users = DB::table('users')->whereIn('id', $rows->pluck('updated_by')->merge($rows->pluck('created_by'))->filter()->unique())->pluck('name', 'id');

        $out = $rows->map(function (QtLineTarget $t) use ($lines, $jobs, $done, $users) {
            $line = $lines->get($t->section.':'.$t->line_id);
            $job = $jobs->get($t->job_id);
            $pass = $done[$t->section.'|'.$t->line_id.'|'.$t->job_id] ?? 0;
            $hours = (float) $t->working_hours;

            return [
                'id' => $t->id, 'target_date' => $t->target_date->toDateString(), 'section' => $t->section, 'line_id' => $t->line_id,
                'line' => QtBook::label($line), 'line_code' => $line?->code, 'job_id' => $t->job_id, 'job' => $job ? $this->jobLabel($job) : null,
                'job_no' => $job?->job_no, 'buyer_name' => $job?->buyer_name, 'style' => $job?->style, 'smv' => $job?->smv === null ? null : (float) $job->smv,
                'working_hours' => $hours, 'manpower' => $t->manpower, 'target_qty' => $t->target_qty,
                'hourly_target' => $hours > 0 ? (int) round($t->target_qty / $hours) : null,
                'pass' => $pass, 'achievement' => $t->target_qty > 0 ? round($pass / $t->target_qty * 100, 2) : null,
                'remarks' => $t->remarks, 'updated_by' => $users[$t->updated_by ?? $t->created_by] ?? null, 'updated_at' => $t->updated_at?->toIso8601String(),
            ];
        })->sortBy(fn ($r) => $r['section'].'|'.$r['line_code'].'|'.$r['job_no'])->values()->all();

        return ApiResponse::ok(['date' => $date, 'rows' => $out]);
    }

    /**
     * The orders a section still has to finish, the nearest due date first:
     * what is done, what is left, what the section before has ready, what is
     * already planned on the day — the manager gives a line its target from
     * here (user, 2026-11-25).
     */
    public function pending(Request $request): JsonResponse
    {
        $this->allowView((string) $request->input('section'));
        $q = $request->validate(['date' => ['nullable', 'date'], 'section' => ['required', Rule::in(QtBook::SECTIONS)]]);
        $c = $this->caller()->companyId;
        $date = CarbonImmutable::parse($q['date'] ?? QtBook::today())->toDateString();
        $section = $q['section'];
        $jobs = QtJob::query()->where('status', 'OPEN')->orderByRaw('due_date is null')->orderBy('due_date')->orderBy('job_no')->get();
        $progress = QtFlow::progress($c, $jobs->pluck('id')->all());
        $lines = QtBook::lines($c, $section);
        $targets = QtLineTarget::query()->whereDate('target_date', $date)->where('section', $section)->get()->groupBy('job_id');

        $rows = [];
        foreach ($jobs as $j) {
            $p = $progress[$j->id];
            $done = QtFlow::passed($p, $section);
            $left = max(0, $j->order_qty - $done);
            if ($left === 0) {
                continue;
            }
            $mine = $targets->get($j->id, collect());
            $planned = (int) $mine->sum('target_qty');
            $ready = QtFlow::waiting($p, $section);
            // Cutting is targeted in panels (its QC checks panels); the other sections in pieces.
            $unit = $section === 'CUTTING' ? $p['panels'] : 1;
            $want = max(0, $left * $unit - $planned);
            $rows[] = $this->job($j) + [
                'done' => $done, 'left' => $left, 'ready' => $ready, 'given' => $section === 'CUTTING' ? $p['bundle_qty'] : null, 'panels' => $section === 'CUTTING' ? $p['panels'] : null,
                'checked' => $p[$section]['checked'], 'reject' => $p[$section]['reject'],
                'planned' => $planned, 'planned_lines' => $mine->map(fn (QtLineTarget $t) => QtBook::label($lines->get($t->line_id)) ?? '—')->values()->all(),
                'suggest' => $want === 0 ? null : ($ready !== null && $ready > 0 ? min($want, $ready) : $want),
            ];
        }

        return ApiResponse::ok(['date' => $date, 'section' => $section, 'rows' => $rows]);
    }

    public function saveTarget(Request $request, ?string $id = null): JsonResponse
    {
        // Each section's manager sets its own targets (user, 2026-11-25).
        $this->allowManage((string) $request->input('section'));
        $c = $this->caller()->companyId;
        $row = $id ? QtLineTarget::query()->findOrFail($id) : null;
        if ($row) {
            $this->allowManage($row->section);
        }
        $d = $request->validate([
            'target_date' => ['required', 'date'],
            'section' => ['required', Rule::in(QtBook::SECTIONS)],
            'line_id' => ['required', 'string', 'size:26'],
            'job_id' => ['required', 'string', 'size:26'],
            'working_hours' => ['required', 'numeric', 'gt:0', 'max:24'],
            'manpower' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'target_qty' => ['required', 'integer', 'min:1', 'max:9999999'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ], ['line_id.required' => __('qtally.v.line'), 'job_id.required' => __('qtally.v.job')], __('qtally.attrs'));
        if (! QtBook::lines($c, $d['section'])->has($d['line_id'])) {
            throw ValidationException::withMessages(['line_id' => __('qtally.v.line')]);
        }
        $job = QtJob::query()->find($d['job_id']);
        if ($job === null || ($job->status !== 'OPEN' && $job->id !== $row?->job_id)) {
            throw ValidationException::withMessages(['job_id' => __('qtally.v.job_open')]);
        }
        $dupe = QtLineTarget::query()->whereDate('target_date', $d['target_date'])->where('section', $d['section'])->where('line_id', $d['line_id'])->where('job_id', $d['job_id'])
            ->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
        if ($dupe) {
            throw ValidationException::withMessages(['job_id' => __('qtally.v.target_dupe')]);
        }
        $me = $this->caller()->auditUserId();
        $saved = $row ? tap($row)->update($d + ['updated_by' => $me]) : QtLineTarget::query()->create($d + ['created_by' => $me, 'updated_by' => $me]);

        return $id ? ApiResponse::ok(['id' => $saved->id]) : ApiResponse::created(['id' => $saved->id]);
    }

    public function deleteTarget(string $id): JsonResponse
    {
        $row = QtLineTarget::query()->findOrFail($id);
        $this->allowManage($row->section);
        $row->delete();

        return ApiResponse::ok(['id' => $id]);
    }

    /** Copies one day's targets to another day (lines and jobs that are still open), skipping those already set. */
    public function copyTargets(Request $request): JsonResponse
    {
        // Every section at once is the all-section manager's; one section is its own manager's.
        $request->filled('section') ? $this->allowManage((string) $request->input('section')) : $this->allow('qtally.target.manage');
        $d = $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'different:from'], 'section' => ['nullable', Rule::in(QtBook::SECTIONS)]]);
        $open = QtJob::query()->where('status', 'OPEN')->pluck('id')->all();
        $me = $this->caller()->auditUserId();
        $copied = 0;
        DB::transaction(function () use ($d, $open, $me, &$copied) {
            $rows = QtLineTarget::query()->whereDate('target_date', $d['from'])->when($d['section'] ?? null, fn ($w, $s) => $w->where('section', $s))->whereIn('job_id', $open)->get();
            foreach ($rows as $t) {
                $exists = QtLineTarget::query()->whereDate('target_date', $d['to'])->where('section', $t->section)->where('line_id', $t->line_id)->where('job_id', $t->job_id)->exists();
                if (! $exists) {
                    QtLineTarget::query()->create(['target_date' => $d['to'], 'section' => $t->section, 'line_id' => $t->line_id, 'job_id' => $t->job_id, 'working_hours' => $t->working_hours,
                        'manpower' => $t->manpower, 'target_qty' => $t->target_qty, 'remarks' => $t->remarks, 'created_by' => $me, 'updated_by' => $me]);
                    $copied++;
                }
            }
        });

        return ApiResponse::ok(['copied' => $copied]);
    }

    /* ── QC Assignment ──────────────────────────────────────────────── */

    public function inspectors(): JsonResponse
    {
        $this->allowEither(self::VIEW, 'qtally.inspector.manage');
        $c = $this->caller()->companyId;
        $lines = QtBook::allLines($c);
        $rows = QtInspector::query()->with('lines')->get();
        $users = DB::table('users')->whereIn('id', $rows->pluck('user_id'))->get(['id', 'name', 'email'])->keyBy('id');
        $employees = HrEmployee::query()->whereIn('id', $rows->pluck('hr_employee_id')->filter())->get(['id', 'employee_code', 'name', 'designation_id'])->keyBy('id');
        $designations = DB::table('designations')->whereIn('id', $employees->pluck('designation_id')->filter())->pluck('name', 'id');
        $today = QtEntry::query()->whereDate('production_date', QtBook::today())->select('user_id', DB::raw('count(*) as n'))->groupBy('user_id')->pluck('n', 'user_id');

        $out = $rows->map(function (QtInspector $i) use ($users, $employees, $designations, $lines, $today) {
            $u = $users->get($i->user_id);
            $e = $employees->get($i->hr_employee_id);

            return [
                'id' => $i->id, 'user_id' => $i->user_id, 'user' => $u?->name, 'email' => $u?->email,
                'hr_employee_id' => $i->hr_employee_id, 'employee' => $e?->name, 'employee_code' => $e?->employee_code, 'designation' => $e ? ($designations[$e->designation_id] ?? null) : null,
                'active' => $i->active, 'pin' => $e === null ? null : (QtPins::isDefault($i) ? 'DEFAULT' : 'OWN'),
                'lines' => $i->lines->map(fn (QtInspectorLine $l) => ['section' => $l->section, 'line_id' => $l->line_id, 'label' => QtBook::label($lines->get($l->section.':'.$l->line_id)) ?? '—'])
                    ->sortBy(fn ($l) => $l['section'].'|'.$l['label'])->values()->all(),
                'taps_today' => (int) ($today[$i->user_id] ?? 0),
            ];
        })->sortBy(fn ($r) => mb_strtolower((string) ($r['employee'] ?? $r['user'])))->values()->all();

        return ApiResponse::ok($out);
    }

    /** The users and HR employees a QC is picked from. */
    public function inspectorOptions(): JsonResponse
    {
        $this->allow('qtally.inspector.manage');
        $c = $this->caller()->companyId;
        $taken = QtInspector::query()->get(['id', 'user_id', 'hr_employee_id']);
        $users = DB::table('company_users as cu')->join('users as u', 'u.id', '=', 'cu.user_id')
            ->where('cu.company_id', $c)->where('cu.status', 'ACTIVE')->where('u.status', 'ACTIVE')
            ->orderBy('u.name')->get(['u.id', 'u.name', 'u.email']);
        $employees = HrEmployee::query()->where('status', 'ACTIVE')->orderBy('name')->get(['id', 'employee_code', 'name', 'email', 'designation_id', 'department_id']);
        $designations = DB::table('designations')->whereIn('id', $employees->pluck('designation_id')->filter())->pluck('name', 'id');
        $departments = DB::table('departments')->whereIn('id', $employees->pluck('department_id')->filter())->pluck('name', 'id');

        return ApiResponse::ok([
            'users' => $users->map(fn ($u) => ['value' => $u->id, 'label' => $u->name.' ('.$u->email.')', 'email' => $u->email, 'name' => $u->name, 'inspector_id' => $taken->firstWhere('user_id', $u->id)?->id])->all(),
            'employees' => $employees->map(fn (HrEmployee $e) => [
                'value' => $e->id, 'label' => $e->employee_code.' — '.$e->name, 'email' => $e->email, 'name' => $e->name,
                'designation' => $designations[$e->designation_id] ?? null, 'department' => $departments[$e->department_id] ?? null,
                'inspector_id' => $taken->firstWhere('hr_employee_id', $e->id)?->id,
            ])->all(),
        ]);
    }

    public function saveInspector(Request $request, ?string $id = null): JsonResponse
    {
        $this->allow('qtally.inspector.manage');
        $c = $this->caller()->companyId;
        $row = $id ? QtInspector::query()->findOrFail($id) : null;
        $d = $request->validate([
            'user_id' => ['required', 'string', 'size:26', Rule::unique('qt_inspectors', 'user_id')->where('company_id', $c)->ignore($row?->id)],
            'hr_employee_id' => ['nullable', 'string', 'size:26', Rule::unique('qt_inspectors', 'hr_employee_id')->where('company_id', $c)->ignore($row?->id)],
            'active' => ['sometimes', 'boolean'],
            'lines' => ['present', 'array', 'max:60'],
            'lines.*.section' => ['required', Rule::in(QtBook::SECTIONS)],
            'lines.*.line_id' => ['required', 'string', 'size:26'],
        ], ['user_id.unique' => __('qtally.v.inspector_user_taken'), 'hr_employee_id.unique' => __('qtally.v.inspector_employee_taken')], __('qtally.attrs'));
        $member = DB::table('company_users')->where('company_id', $c)->where('user_id', $d['user_id'])->where('status', 'ACTIVE')->exists();
        if (! $member) {
            throw ValidationException::withMessages(['user_id' => __('qtally.v.user')]);
        }
        if (($d['hr_employee_id'] ?? null) && ! HrEmployee::query()->whereKey($d['hr_employee_id'])->exists()) {
            throw ValidationException::withMessages(['hr_employee_id' => __('qtally.v.employee')]);
        }
        $lines = collect($d['lines'])->unique(fn ($l) => $l['section'].':'.$l['line_id'])->values();
        foreach ($lines as $i => $l) {
            if (! QtBook::lines($c, $l['section'])->has($l['line_id'])) {
                throw ValidationException::withMessages(["lines.{$i}.line_id" => __('qtally.v.line')]);
            }
        }
        $me = $this->caller()->auditUserId();
        $saved = DB::transaction(function () use ($row, $d, $lines, $me) {
            $values = ['user_id' => $d['user_id'], 'hr_employee_id' => $d['hr_employee_id'] ?? null, 'active' => $d['active'] ?? true, 'updated_by' => $me];
            $saved = $row ? tap($row)->update($values) : QtInspector::query()->create($values + ['created_by' => $me]);
            QtInspectorLine::query()->where('inspector_id', $saved->id)->delete();
            foreach ($lines as $l) {
                QtInspectorLine::query()->create(['inspector_id' => $saved->id, 'section' => $l['section'], 'line_id' => $l['line_id']]);
            }

            // Another employee's QC, or an inactive one: the phones they are signed in on are signed out, the PIN starts again.
            if ($row !== null && ($saved->wasChanged('hr_employee_id') || $saved->wasChanged('user_id'))) {
                QtPins::set($saved, null);
                QtPhones::endFor($saved, 'REMOVED');
            } elseif (! $saved->active) {
                QtPhones::endFor($saved, 'REMOVED');
            }

            return $saved;
        });

        return $id ? ApiResponse::ok(['id' => $saved->id]) : ApiResponse::created(['id' => $saved->id]);
    }

    /** Back to 000000 (a forgotten PIN): the phones the QC is signed in on are signed out. */
    public function resetPin(string $id): JsonResponse
    {
        $this->allow('qtally.inspector.manage');
        $row = QtInspector::query()->findOrFail($id);
        DB::transaction(function () use ($row): void {
            QtPins::set($row, null);
            QtPhones::endFor($row, 'PIN_RESET');
        });

        return ApiResponse::ok(['id' => $id]);
    }

    public function deleteInspector(string $id): JsonResponse
    {
        $this->allow('qtally.inspector.manage');
        $row = QtInspector::query()->findOrFail($id);
        QtPhones::endFor($row, 'REMOVED');
        // Taps keep their user; the QC row only goes (entries.inspector_id is set null).
        $row->delete();

        return ApiResponse::ok(['id' => $id]);
    }

    /* ── Plan the day: every line's targets at once, and the shortfall ── */

    /** Saves a section's targets for a day in one go (a row with no target is removed). */
    public function bulkTargets(Request $request): JsonResponse
    {
        $this->allowManage((string) $request->input('section'));
        $c = $this->caller()->companyId;
        $d = $request->validate([
            'target_date' => ['required', 'date'],
            'section' => ['required', Rule::in(QtBook::SECTIONS)],
            'rows' => ['present', 'array', 'max:300'],
            'rows.*.line_id' => ['required', 'string', 'size:26'],
            'rows.*.job_id' => ['required', 'string', 'size:26'],
            'rows.*.working_hours' => ['required', 'numeric', 'gt:0', 'max:24'],
            'rows.*.manpower' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'rows.*.target_qty' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'rows.*.remarks' => ['nullable', 'string', 'max:1000'],
        ], [], __('qtally.attrs'));
        $lines = QtBook::lines($c, $d['section']);
        $open = QtJob::query()->where('status', 'OPEN')->pluck('id')->flip();
        foreach ($d['rows'] as $i => $r) {
            if (! $lines->has($r['line_id'])) {
                throw ValidationException::withMessages(["rows.{$i}.line_id" => __('qtally.v.line')]);
            }
            $already = QtLineTarget::query()->whereDate('target_date', $d['target_date'])->where('line_id', $r['line_id'])->where('job_id', $r['job_id'])->exists();
            if (! $open->has($r['job_id']) && ! $already) {
                throw ValidationException::withMessages(["rows.{$i}.job_id" => __('qtally.v.job_open')]);
            }
        }
        $me = $this->caller()->auditUserId();
        $saved = 0;
        $removed = 0;
        DB::transaction(function () use ($d, $me, &$saved, &$removed) {
            foreach ($d['rows'] as $r) {
                $row = QtLineTarget::query()->whereDate('target_date', $d['target_date'])->where('section', $d['section'])->where('line_id', $r['line_id'])->where('job_id', $r['job_id'])->first();
                if (empty($r['target_qty'])) {
                    if ($row) {
                        $row->delete();
                        $removed++;
                    }

                    continue;
                }
                $values = ['working_hours' => $r['working_hours'], 'manpower' => $r['manpower'] ?? null, 'target_qty' => $r['target_qty'], 'remarks' => $r['remarks'] ?? null, 'updated_by' => $me];
                if ($row) {
                    $row->update($values);
                } else {
                    QtLineTarget::query()->create(['target_date' => $d['target_date'], 'section' => $d['section'], 'line_id' => $r['line_id'], 'job_id' => $r['job_id']] + $values + ['created_by' => $me]);
                }
                $saved++;
            }
        });

        return ApiResponse::ok(['saved' => $saved, 'removed' => $removed]);
    }

    /**
     * What each line was asked for on its LAST SCHEDULED day before this one,
     * and what it passed. Surfaced, never rolled over: a missed target added
     * to the next day hides whether the plan or the line was at fault and
     * snowballs — the planner decides (annotech-rmg ShortfallService).
     */
    public function shortfall(Request $request): JsonResponse
    {
        $this->allowView((string) $request->input('section'));
        $q = $request->validate(['date' => ['required', 'date'], 'section' => ['required', Rule::in(QtBook::SECTIONS)]]);
        $c = $this->caller()->companyId;
        $lines = QtBook::lines($c, $q['section']);
        $last = QtLineTarget::query()->where('section', $q['section'])->whereDate('target_date', '<', $q['date'])
            ->select('line_id', DB::raw('max(target_date) as d'))->groupBy('line_id')->pluck('d', 'line_id');
        $out = [];
        foreach ($last as $lineId => $day) {
            $day = CarbonImmutable::parse($day)->toDateString();
            $targets = QtLineTarget::query()->whereDate('target_date', $day)->where('section', $q['section'])->where('line_id', $lineId)->get();
            $done = $this->passBy($day, $targets);
            foreach ($targets as $t) {
                $pass = $done[$t->section.'|'.$t->line_id.'|'.$t->job_id] ?? 0;
                if ($pass < $t->target_qty) {
                    $out[] = [
                        'line_id' => $lineId, 'line' => QtBook::label($lines->get($lineId)), 'job_id' => $t->job_id, 'day' => $day,
                        'target' => $t->target_qty, 'pass' => $pass, 'shortfall' => $t->target_qty - $pass,
                    ];
                }
            }
        }

        return ApiResponse::ok($out);
    }

    /* ── Line Attendance ────────────────────────────────────────────── */

    public function attendance(Request $request): JsonResponse
    {
        $this->allowView((string) $request->input('section', 'SEWING'));
        $q = $request->validate(['date' => ['nullable', 'date'], 'section' => ['nullable', Rule::in(QtBook::SECTIONS)]]);
        $c = $this->caller()->companyId;
        $date = CarbonImmutable::parse($q['date'] ?? QtBook::today())->toDateString();
        $section = $q['section'] ?? 'SEWING';
        $shifts = QtBook::shifts($c);
        $rows = QtLineAttendance::query()->whereDate('att_date', $date)->where('section', $section)->get()->groupBy('line_id');
        $targets = QtLineTarget::query()->whereDate('target_date', $date)->where('section', $section)->get()->groupBy('line_id');
        $users = DB::table('users')->whereIn('id', $rows->flatten()->pluck('set_by')->filter()->unique())->pluck('name', 'id');

        return ApiResponse::ok([
            'date' => $date, 'section' => $section, 'can_manage' => QtFlow::manages($this->caller(), $section),
            'shifts' => $shifts->map(fn ($s) => ['id' => $s->id, 'label' => $s->name, 'time' => \App\Shared\Support\Clock::range($s->start, $s->end), 'minutes' => $s->minutes])->values()->all(),
            'lines' => QtBook::lines($c, $section)->where('active', true)->map(function ($l) use ($rows, $targets, $users) {
                $mine = $rows->get($l->id, collect());
                $latest = $mine->sortByDesc('updated_at')->first();

                return [
                    'line_id' => $l->id, 'line' => QtBook::label($l), 'code' => $l->code,
                    'target_manpower' => $targets->get($l->id)?->max('manpower'),
                    'has_target' => $targets->has($l->id),
                    'entries' => $mine->mapWithKeys(fn (QtLineAttendance $a) => [$a->shift_id => ['attendance' => $a->attendance, 'overtime_minutes' => $a->overtime_minutes]])->all(),
                    'set_by' => $latest ? ($users[$latest->set_by] ?? null) : null,
                ];
            })->values()->all(),
        ]);
    }

    /** Saves a section's attendance for a day: each line and shift; 0 people removes the row. */
    public function saveAttendance(Request $request): JsonResponse
    {
        $this->allowManage((string) $request->input('section'));
        $c = $this->caller()->companyId;
        $d = $request->validate([
            'date' => ['required', 'date'],
            'section' => ['required', Rule::in(QtBook::SECTIONS)],
            'rows' => ['present', 'array', 'max:600'],
            'rows.*.line_id' => ['required', 'string', 'size:26'],
            'rows.*.shift_id' => ['present', 'nullable', 'string', 'max:26'],
            'rows.*.attendance' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'rows.*.overtime_minutes' => ['nullable', 'integer', 'min:0', 'max:720'],
        ], [], __('qtally.attrs'));
        $lines = QtBook::lines($c, $d['section']);
        $shifts = QtBook::shifts($c);
        foreach ($d['rows'] as $i => $r) {
            if (! $lines->has($r['line_id'])) {
                throw ValidationException::withMessages(["rows.{$i}.line_id" => __('qtally.v.line')]);
            }
            if (($r['shift_id'] ?? '') !== '' && ! $shifts->has($r['shift_id'])) {
                throw ValidationException::withMessages(["rows.{$i}.shift_id" => __('qtally.v.shift')]);
            }
        }
        $me = $this->caller()->auditUserId();
        DB::transaction(function () use ($d, $me) {
            foreach ($d['rows'] as $r) {
                $shift = $r['shift_id'] ?? '';
                $row = QtLineAttendance::query()->whereDate('att_date', $d['date'])->where('section', $d['section'])->where('line_id', $r['line_id'])->where('shift_id', $shift)->first();
                if (empty($r['attendance'])) {
                    $row?->delete();

                    continue;
                }
                $values = ['attendance' => $r['attendance'], 'overtime_minutes' => $r['overtime_minutes'] ?? 0, 'set_by' => $me];
                if ($row) {
                    $row->update($values);
                } else {
                    QtLineAttendance::query()->create(['att_date' => $d['date'], 'section' => $d['section'], 'line_id' => $r['line_id'], 'shift_id' => $shift] + $values);
                }
            }
        });

        return ApiResponse::ok(['saved' => count($d['rows'])]);
    }

    /* ── helpers ────────────────────────────────────────────────────── */

    /** @return array<string, int> "SECTION|line|job" => pass on that day */
    private function passBy(string $date, $targets): array
    {
        if ($targets->isEmpty()) {
            return [];
        }
        $rows = QtEntry::query()->whereDate('production_date', $date)->whereIn('job_id', $targets->pluck('job_id')->unique())
            ->select('section', 'line_id', 'job_id', 'action', DB::raw('count(*) as n'))->groupBy('section', 'line_id', 'job_id', 'action')->get()
            ->groupBy(fn ($r) => $r->section.'|'.$r->line_id.'|'.$r->job_id);

        return $rows->map(fn ($g) => QtBook::metrics($g->pluck('n', 'action')->map(fn ($n) => (int) $n)->all(), 0)['pass'])->all();
    }

    private function jobLabel(QtJob $j): string
    {
        return trim($j->job_no.' · '.$j->buyer_name.' · '.$j->style.($j->color !== '' ? ' · '.$j->color : '').($j->size !== '' ? ' · '.$j->size : '').($j->po_no !== '' ? ' · PO '.$j->po_no : ''));
    }

    /** @return array<string, mixed> */
    private function job(QtJob $j): array
    {
        return [
            'id' => $j->id, 'job_no' => $j->job_no, 'buyer_name' => $j->buyer_name, 'style' => $j->style, 'po_no' => $j->po_no, 'color' => $j->color, 'size' => $j->size,
            'due_date' => $j->due_date?->toDateString(), 'days_left' => $j->due_date ? (int) QtBook::now()->startOfDay()->diffInDays($j->due_date, false) : null,
            'order_qty' => $j->order_qty, 'opening_cut' => $j->opening_cut ?? 0, 'opening_sewing' => $j->opening_sewing ?? 0, 'opening_quality' => $j->opening_quality ?? 0,
            'smv' => $j->smv === null ? null : (float) $j->smv, 'status' => $j->status, 'remarks' => $j->remarks, 'label' => $this->jobLabel($j),
        ];
    }
}
