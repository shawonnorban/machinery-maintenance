<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Http\Controllers\Api;

use App\Modules\HrPayroll\Support\HrDefaults;
use App\Modules\HrPayroll\Support\AttendanceBook;
use App\Modules\HrPayroll\Support\OtBook;
use App\Modules\Identity\Models\User;
use App\Modules\Notification\Services\NotificationDispatcher;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Support\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * HR & Payroll → Leave & Overtime, the OT screens (AnnotechERP 24/05), as
 * Brickland lays them out:
 *
 * - **OT Correction:** OT is paid from attendance; a day's OT hours or rate
 *   is corrected here with a reason, and every save or reset is kept.
 * - **OT Eligibility:** the OT limits; which grades earn General OT (each
 *   switch with a reason); Worker OT switched on for staff, with a note.
 * - **OT Request:** OT asked for a line on a day — all of the line or
 *   picked employees — with the window and reason, approved directly by
 *   whoever holds OT Approval or sent to an approver; a returned request is
 *   edited and sent again.
 * - **OT Approval:** my inbox, all pending, decided; approve (unticking
 *   anyone), reject or return with a note.
 *
 * OT is paid from attendance; where a request for the day was approved,
 * only the time inside its window is paid.
 */
class HrOvertimeApiController extends ApiController
{
    private const TZ = 'Asia/Dhaka';

    private const MAX_REQUESTS = 200;

    public function options(): JsonResponse
    {
        $this->allowEither('hrpayroll.ot.view', 'hrpayroll.ot.manage', 'hrpayroll.ot.approve');
        $c = $this->caller()->companyId;

        return ApiResponse::ok([
            'today' => CarbonImmutable::now(self::TZ)->toDateString(),
            'departments' => DB::table('departments')->where('company_id', $c)->where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name'])->map(fn ($x) => ['value' => $x->id, 'label' => $x->name])->all(),
            'sections' => DB::table('sections')->where('company_id', $c)->where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name', 'department_id'])->map(fn ($x) => ['value' => $x->id, 'label' => $x->name, 'department_id' => $x->department_id])->all(),
            'lines' => DB::table('production_lines')->where('company_id', $c)->where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name', 'section_id', 'department_id'])->map(fn ($x) => ['value' => $x->id, 'label' => $x->name, 'section_id' => $x->section_id, 'department_id' => $x->department_id])->all(),
            'shifts' => (new AttendanceBook($c))->shifts()->values()->map(fn ($s) => ['value' => $s->id, 'label' => $s->name.' ('.Clock::range($s->start_time, $s->end_time).')', 'start' => $s->start_time, 'end' => AttendanceBook::regularEnd($s), 'ot_allowed' => (bool) $s->ot_allowed])->all(),
            'approvers' => $this->approvers()->map(fn ($u) => ['value' => $u->id, 'label' => $u->name])->values()->all(),
            'can_request' => $this->caller()->can('hrpayroll.ot.manage'),
            'can_approve' => $this->caller()->can('hrpayroll.ot.approve'),
        ]);
    }

    /* ── OT Eligibility ────────────────────────────────────────────── */

    public function eligibility(Request $request): JsonResponse
    {
        $this->allowEither('hrpayroll.ot.view', 'hrpayroll.ot.manage');
        $c = $this->caller()->companyId;
        $book = new OtBook($c);
        $q = trim((string) $request->query('q', ''));
        $employees = $this->staff()->when($request->query('department'), fn ($x, $d) => $x->where('e.department_id', $d))
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w->where('e.employee_code', 'ilike', "%{$q}%")->orWhere('e.name', 'ilike', "%{$q}%")->orWhere('e.card_no', 'ilike', "%{$q}%")))
            ->get();
        $standing = $book->eligibility($employees);
        $rows = $employees->map(function ($e) use ($standing) {
            $s = $standing[$e->id];

            return [
                'code' => $e->employee_code, 'name' => $e->name, 'department' => $e->department, 'designation' => $e->designation, 'grade' => $e->grade,
                'category' => $s['category'], 'general_ot' => $s['general'], 'general_reason' => $s['general_reason'],
                'worker_ot_allowed' => $s['worker_allowed'], 'worker_ot' => $s['worker_ot'], 'worker_ot_eligible' => $s['type'] === 'WORKER',
                'worker_rule' => $s['worker_rule_text'], 'note' => $s['note'], 'ot' => $s['type'] ?? 'NONE',
            ];
        })
            ->when($request->query('category'), fn ($x, $cat) => $x->where('category', $cat))
            ->when($request->query('ot'), fn ($x, $ot) => $x->where('ot', $ot))
            ->values();
        $limits = $book->limits();
        $grades = DB::table('grades')->where('company_id', $c)->where('status', 'ACTIVE')->orderBy('sort_order')->orderBy('code')->get(['id', 'code', 'name']);
        $counts = DB::table('hr_employees')->where('company_id', $c)->where('status', 'ACTIVE')->whereNotNull('grade_id')->groupBy('grade_id')->selectRaw('grade_id, count(*) as n')->pluck('n', 'grade_id');
        $off = DB::table('hr_grade_ot')->where('company_id', $c)->pluck('general_ot', 'grade_id');

        return ApiResponse::ok([
            'rows' => $rows->all(),
            'summary' => ['employees' => $rows->count(), 'general_ot' => $rows->where('ot', 'GENERAL')->count(), 'staff' => $rows->where('ot', '!=', 'GENERAL')->where('worker_ot', false)->count(), 'worker_ot' => $rows->where('ot', 'WORKER')->count()],
            'limits' => collect(OtBook::LIMIT_KEYS)->mapWithKeys(fn ($k) => [$k => (float) $limits->{$k}])->all()
                + ['updated_by' => $limits->updated_by ? DB::table('users')->where('id', $limits->updated_by)->value('name') : null, 'updated_at' => $limits->updated_by ? (string) $limits->updated_at : null],
            'grades' => $grades->map(fn ($g) => ['code' => $g->code, 'grade' => $g->name ?: $g->code, 'employees' => (int) ($counts[$g->id] ?? 0), 'general_ot' => (bool) ($off[$g->id] ?? true)])->all(),
        ]);
    }

    public function saveLimits(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.ot.manage');
        $whole = fn (string $attr, mixed $v, $fail) => (int) $v % 60 === 0 ?: $fail(__('hrpayroll.lo.v.rounding'));
        $d = $request->validate([
            'start_after_min' => ['required', 'integer', 'min:0', 'max:600'], 'grace_min' => ['required', 'integer', 'min:0', 'max:59'],
            'general_rounding_min' => ['required', 'integer', 'min:60', 'max:600', $whole], 'worker_rounding_min' => ['required', 'integer', 'min:60', 'max:600', $whole],
            'break_min' => ['required', 'integer', 'min:0', 'max:240'], 'break_after_hours' => ['required', 'numeric', 'min:0', 'max:24'],
            'min_minutes' => ['required', 'integer', 'min:0', 'max:600'], 'max_hours_day' => ['required', 'numeric', 'min:0', 'max:24'],
        ], ['*.min' => __('hrpayroll.lo.v.limits'), '*.integer' => __('hrpayroll.lo.v.limits'), '*.numeric' => __('hrpayroll.lo.v.limits'), '*.required' => __('hrpayroll.lo.v.limits'), 'general_rounding_min.min' => __('hrpayroll.lo.v.rounding'), 'worker_rounding_min.min' => __('hrpayroll.lo.v.rounding')]);
        $c = $this->caller()->companyId;
        $before = (array) (new OtBook($c))->limits();
        DB::table('hr_ot_limits')->updateOrInsert(['company_id' => $c], $d + ['id' => DB::table('hr_ot_limits')->where('company_id', $c)->value('id') ?? strtolower((string) Str::ulid()), 'updated_by' => $this->caller()->auditUserId(), 'created_at' => now(), 'updated_at' => now()]);
        $changed = collect($d)->filter(fn ($v, $k) => (float) ($before[$k] ?? 0) !== (float) $v)->map(fn ($v, $k) => "{$k}: ".(float) ($before[$k] ?? 0)." → {$v}")->implode('; ');
        $this->audit('LIMITS', null, $changed ?: '—', null);

        return ApiResponse::ok(['message' => __('hrpayroll.lo.done.limits')]);
    }

    public function saveGrade(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.ot.manage');
        $c = $this->caller()->companyId;
        $d = $request->validate(['grade' => ['required', 'string'], 'general_ot' => ['required', 'boolean'], 'reason' => ['required', 'string', 'max:300']], ['reason.required' => __('hrpayroll.lo.v.grade_reason')]);
        $grade = DB::table('grades')->where('company_id', $c)->where('code', $d['grade'])->first();
        abort_unless($grade, 404);
        DB::table('hr_grade_ot')->updateOrInsert(['company_id' => $c, 'grade_id' => $grade->id], ['id' => DB::table('hr_grade_ot')->where('company_id', $c)->where('grade_id', $grade->id)->value('id') ?? strtolower((string) Str::ulid()), 'general_ot' => (bool) $d['general_ot'], 'updated_by' => $this->caller()->auditUserId(), 'created_at' => now(), 'updated_at' => now()]);
        $this->audit('GRADE', $grade->id, "Grade {$grade->code}: General OT ".($d['general_ot'] ? 'on' : 'off'), $d['reason']);

        return ApiResponse::ok(['message' => __($d['general_ot'] ? 'hrpayroll.lo.done.grade_on' : 'hrpayroll.lo.done.grade_off', ['grade' => $grade->code])]);
    }

    public function saveEmployee(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.ot.manage');
        $c = $this->caller()->companyId;
        $d = $request->validate(['employee' => ['required', 'string'], 'worker_ot' => ['required', 'boolean'], 'note' => ['nullable', 'string', 'max:200']]);
        $e = DB::table('hr_employees')->where('company_id', $c)->where('employee_code', $d['employee'])->first();
        abort_unless($e, 404);
        if (((new OtBook($c))->eligibility(collect([$e]))[$e->id]['category'] ?? null) !== 'Staff') {
            throw ValidationException::withMessages(['worker_ot' => __('hrpayroll.lo.v.staff_only')]);
        }
        DB::table('hr_employee_ot')->updateOrInsert(['employee_id' => $e->id], ['id' => DB::table('hr_employee_ot')->where('employee_id', $e->id)->value('id') ?? strtolower((string) Str::ulid()), 'company_id' => $c, 'worker_ot' => (bool) $d['worker_ot'], 'note' => $d['note'] ?? null, 'updated_by' => $this->caller()->auditUserId(), 'created_at' => now(), 'updated_at' => now()]);
        $this->audit('EMPLOYEE', $e->id, "{$e->employee_code}: Worker OT ".($d['worker_ot'] ? 'on' : 'off'), $d['note'] ?? null);

        return ApiResponse::ok(['message' => __('hrpayroll.lo.done.worker_ot', ['code' => $e->employee_code])]);
    }

    /* ── OT Correction ─────────────────────────────────────────────── */

    public function correction(Request $request): JsonResponse
    {
        $this->allowEither('hrpayroll.ot.view', 'hrpayroll.ot.manage');
        $c = $this->caller()->companyId;
        $d = $request->validate(['date' => ['nullable', 'date', 'before_or_equal:today'], 'department' => ['nullable', 'string'], 'q' => ['nullable', 'string', 'max:60'], 'show' => ['nullable', Rule::in(['ot', 'all'])]]);
        $date = $d['date'] ?? CarbonImmutable::now(self::TZ)->toDateString();
        (new AttendanceBook($c))->process($date, $date);
        $q = trim((string) ($d['q'] ?? ''));
        $days = $this->days($date)->when($d['department'] ?? null, fn ($x, $dep) => $x->where('e.department_id', $dep))
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w->where('e.employee_code', 'ilike', "%{$q}%")->orWhere('e.name', 'ilike', "%{$q}%")->orWhere('e.card_no', 'ilike', "%{$q}%")))
            ->when(($d['show'] ?? 'ot') === 'ot', fn ($x) => $x->where(fn ($w) => $w->where('a.ot_minutes', '>', 0)->orWhereNotNull('k.id')))
            ->orderBy('e.employee_code')->get();
        $rows = $this->otRows($days);

        return ApiResponse::ok([
            'date' => $date, 'rows' => $rows->all(),
            'summary' => [
                'employees' => $rows->filter(fn ($r) => ($r['corrected_hours'] ?? $r['original_hours']) > 0)->count(),
                'original_hours' => round($rows->sum('original_hours'), 2), 'effective_hours' => round($rows->sum(fn ($r) => $r['corrected_hours'] ?? $r['original_hours']), 2),
                'original_amount' => round($rows->sum('original_amount'), 2), 'effective_amount' => round($rows->sum(fn ($r) => $r['corrected_amount'] ?? $r['original_amount']), 2),
                'corrected' => $rows->whereNotNull('corrected_hours')->count(),
            ],
        ]);
    }

    public function saveCorrection(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.ot.manage');
        $d = $request->validate([
            'employee' => ['required', 'string'], 'date' => ['required', 'date', 'before_or_equal:today'],
            'hours' => ['required', 'numeric', 'min:0', 'max:24'], 'rate' => ['required', 'numeric', 'min:0', 'max:100000'], 'reason' => ['required', 'string', 'max:300'],
        ], ['reason.required' => __('hrpayroll.lo.v.correction_reason'), 'hours.*' => __('hrpayroll.lo.v.correction_numbers'), 'rate.*' => __('hrpayroll.lo.v.correction_numbers')]);
        $c = $this->caller()->companyId;
        $e = $this->employee($d['employee']);
        $date = CarbonImmutable::parse($d['date'])->toDateString();
        abort_unless(DB::table('hr_attendance_days')->where('employee_id', $e->id)->where('date', $date)->exists(), 404);
        $id = DB::table('hr_ot_corrections')->where('employee_id', $e->id)->where('date', $date)->value('id');
        $values = ['hours' => round((float) $d['hours'], 2), 'rate' => round((float) $d['rate'], 2), 'reason' => $d['reason'], 'updated_by' => $this->caller()->auditUserId(), 'updated_at' => now()];
        $id ? DB::table('hr_ot_corrections')->where('id', $id)->update($values)
            : DB::table('hr_ot_corrections')->insert($values + ['id' => strtolower((string) Str::ulid()), 'company_id' => $c, 'employee_id' => $e->id, 'date' => $date, 'created_at' => now()]);
        $this->history($e->id, $date, 'SAVE', $values['hours'], $values['rate'], $d['reason']);

        return ApiResponse::ok(['message' => __('hrpayroll.lo.done.corrected', ['code' => $e->employee_code, 'date' => $date]), 'row' => $this->otRows($this->days($date)->where('e.id', $e->id)->get())->first()]);
    }

    public function resetCorrection(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.ot.manage');
        $d = $request->validate(['employee' => ['required', 'string'], 'date' => ['required', 'date'], 'reason' => ['required', 'string', 'max:300']], ['reason.required' => __('hrpayroll.lo.v.correction_reason')]);
        $e = $this->employee($d['employee']);
        $date = CarbonImmutable::parse($d['date'])->toDateString();
        $deleted = DB::table('hr_ot_corrections')->where('employee_id', $e->id)->where('date', $date)->delete();
        abort_unless($deleted, 404);
        $this->history($e->id, $date, 'RESET', null, null, $d['reason']);

        return ApiResponse::ok(['message' => __('hrpayroll.lo.done.reset', ['code' => $e->employee_code, 'date' => $date]), 'row' => $this->otRows($this->days($date)->where('e.id', $e->id)->get())->first()]);
    }

    public function correctionHistory(Request $request): JsonResponse
    {
        $this->allowEither('hrpayroll.ot.view', 'hrpayroll.ot.manage');
        $e = $this->employee((string) $request->query('employee'));
        $date = CarbonImmutable::parse((string) $request->query('date'))->toDateString();
        $rows = DB::table('hr_ot_correction_history as h')->leftJoin('users as u', 'u.id', '=', 'h.user_id')->where('h.employee_id', $e->id)->where('h.date', $date)
            ->orderByDesc('h.created_at')->orderByDesc('h.id')->get(['h.created_at', 'h.action', 'h.hours', 'h.rate', 'h.amount', 'h.reason', 'u.name as by']);

        return ApiResponse::ok($rows->map(fn ($r) => ['when' => (string) $r->created_at, 'by' => $r->by, 'action' => $r->action, 'hours' => $r->hours === null ? null : (float) $r->hours, 'rate' => $r->rate === null ? null : (float) $r->rate, 'amount' => $r->amount === null ? null : (float) $r->amount, 'reason' => $r->reason])->all());
    }

    /* ── OT Request ────────────────────────────────────────────────── */

    public function requests(): JsonResponse
    {
        $this->allowEither('hrpayroll.ot.view', 'hrpayroll.ot.manage', 'hrpayroll.ot.approve');

        return ApiResponse::ok($this->requestRows($this->requestQuery()->limit(self::MAX_REQUESTS)->get()));
    }

    /** The line's employees (active, on the day) and its shift. */
    public function lineEmployees(Request $request): JsonResponse
    {
        $this->allowEither('hrpayroll.ot.manage', 'hrpayroll.ot.view');
        $c = $this->caller()->companyId;
        $date = (string) ($request->query('date') ?: CarbonImmutable::now(self::TZ)->toDateString());
        $line = DB::table('production_lines')->where('company_id', $c)->where('id', (string) $request->query('line'))->first();
        abort_unless($line, 404);
        $all = DB::table('hr_employees as e')->leftJoin('designations as d', 'd.id', '=', 'e.designation_id')->leftJoin('sections as s', 's.id', '=', 'e.section_id')
            ->where('e.company_id', $c)->where('e.line_id', $line->id)->orderBy('e.employee_code')
            ->get(['e.id', 'e.employee_code', 'e.name', 'e.status', 'd.name as designation', 's.name as section']);
        $shifts = $this->shiftsOn($all->pluck('id')->all(), $date);
        $book = new AttendanceBook($c);
        $known = $book->shifts();
        $active = $all->where('status', 'ACTIVE')->values();
        $common = $active->map(fn ($e) => $shifts[$e->id] ?? null)->filter()->countBy()->sortDesc()->keys()->first();

        return ApiResponse::ok([
            'total' => $all->count(), 'shift_id' => $common,
            'employees' => $active->map(fn ($e) => ['code' => $e->employee_code, 'name' => $e->name, 'designation' => $e->designation, 'section' => $e->section, 'shift' => AttendanceBook::label($known[$shifts[$e->id] ?? ''] ?? null)])->all(),
        ]);
    }

    public function storeRequest(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.ot.manage');
        [$d, $employees, $minutes] = $this->validRequest($request, null);
        $c = $this->caller()->companyId;
        $no = $this->nextRequestNo($c);
        $direct = $d['approval'] === 'DIRECT';
        $id = strtolower((string) Str::ulid());
        DB::transaction(function () use ($d, $employees, $minutes, $c, $no, $direct, $id): void {
            DB::table('hr_ot_requests')->insert($this->requestValues($d, $minutes, $direct) + ['id' => $id, 'company_id' => $c, 'request_no' => $no, 'requested_by' => $this->caller()->auditUserId(), 'created_at' => now()]);
            DB::table('hr_ot_request_employees')->insert($employees->map(fn ($e) => ['id' => strtolower((string) Str::ulid()), 'request_id' => $id, 'employee_id' => $e->id, 'removed' => false])->all());
        });
        $this->afterSubmit($id, $direct, $d['date'], $employees);

        return ApiResponse::created(['message' => __($direct ? 'hrpayroll.lo.done.ot_approved' : 'hrpayroll.lo.done.ot_sent', ['no' => $no]), 'row' => $this->requestRows($this->requestQuery()->where('r.id', $id)->get())[0]]);
    }

    /** A returned request edited and sent again. */
    public function updateRequest(Request $request, string $no): JsonResponse
    {
        $this->allow('hrpayroll.ot.manage');
        $row = DB::table('hr_ot_requests')->where('company_id', $this->caller()->companyId)->where('request_no', $no)->first();
        abort_unless($row, 404);
        if ($row->status !== 'RETURNED') {
            throw ValidationException::withMessages(['status' => __('hrpayroll.lo.v.not_returned')]);
        }
        [$d, $employees, $minutes] = $this->validRequest($request, $row->id);
        $direct = $d['approval'] === 'DIRECT';
        DB::transaction(function () use ($row, $d, $employees, $minutes, $direct): void {
            DB::table('hr_ot_requests')->where('id', $row->id)->update($this->requestValues($d, $minutes, $direct) + ['decision_note' => null]);
            DB::table('hr_ot_request_employees')->where('request_id', $row->id)->delete();
            DB::table('hr_ot_request_employees')->insert($employees->map(fn ($e) => ['id' => strtolower((string) Str::ulid()), 'request_id' => $row->id, 'employee_id' => $e->id, 'removed' => false])->all());
        });
        $this->afterSubmit($row->id, $direct, $d['date'], $employees);

        return ApiResponse::ok(['message' => __($direct ? 'hrpayroll.lo.done.ot_approved' : 'hrpayroll.lo.done.ot_sent', ['no' => $no]), 'row' => $this->requestRows($this->requestQuery()->where('r.id', $row->id)->get())[0]]);
    }

    /* ── OT Approval ───────────────────────────────────────────────── */

    public function approvals(): JsonResponse
    {
        $this->allowEither('hrpayroll.ot.view', 'hrpayroll.ot.approve', 'hrpayroll.ot.manage');
        $me = $this->caller()->user?->id;
        $pending = $this->requestRows($this->requestQuery()->where('r.status', 'PENDING')->get());

        return ApiResponse::ok([
            'inbox' => array_values(array_filter($pending, fn ($r) => $r['approver_id'] === $me)),
            'pending' => $pending,
            'decided' => $this->requestRows($this->requestQuery()->whereIn('r.status', ['APPROVED', 'REJECTED', 'RETURNED'])->orderByDesc('r.decided_at')->limit(self::MAX_REQUESTS)->get()),
        ]);
    }

    public function decide(Request $request, string $no): JsonResponse
    {
        $this->allow('hrpayroll.ot.approve');
        $c = $this->caller()->companyId;
        $row = DB::table('hr_ot_requests')->where('company_id', $c)->where('request_no', $no)->first();
        abort_unless($row, 404);
        $d = $request->validate([
            'decision' => ['required', Rule::in(['APPROVE', 'REJECT', 'RETURN'])], 'note' => [Rule::requiredIf(fn () => $request->input('decision') !== 'APPROVE'), 'nullable', 'string', 'max:1000'],
            'removed' => ['nullable', 'array'], 'removed.*' => ['string'],
        ], ['note.required' => __('hrpayroll.lo.v.note')]);
        if ($row->status !== 'PENDING') {
            throw ValidationException::withMessages(['decision' => __('hrpayroll.lo.v.decided')]);
        }
        $people = DB::table('hr_ot_request_employees as x')->join('hr_employees as e', 'e.id', '=', 'x.employee_id')->where('x.request_id', $row->id)->get(['x.id', 'e.id as employee_id', 'e.employee_code']);
        $removed = $d['decision'] === 'APPROVE' ? $people->whereIn('employee_code', $d['removed'] ?? []) : collect();
        if ($d['decision'] === 'APPROVE' && $removed->count() >= $people->count()) {
            throw ValidationException::withMessages(['removed' => __('hrpayroll.lo.v.keep_one')]);
        }
        $status = ['APPROVE' => 'APPROVED', 'REJECT' => 'REJECTED', 'RETURN' => 'RETURNED'][$d['decision']];
        DB::transaction(function () use ($row, $removed, $status, $d): void {
            DB::table('hr_ot_request_employees')->whereIn('id', $removed->pluck('id'))->update(['removed' => true]);
            DB::table('hr_ot_requests')->where('id', $row->id)->update(['status' => $status, 'decision_note' => trim((string) ($d['note'] ?? '')) ?: null, 'decided_by' => $this->caller()->auditUserId(), 'decided_at' => now(), 'updated_at' => now()]);
        });
        if ($status === 'APPROVED') {
            $this->rework(substr((string) $row->date, 0, 10), $people->pluck('employee_id')->all());
        }
        $requester = $row->requested_by && $row->requested_by !== $this->caller()->user?->id ? User::query()->find($row->requested_by) : null;
        if ($requester) {
            app(NotificationDispatcher::class)->send($requester, 'HR_OT_DECIDED', ['no' => $row->request_no, 'line' => DB::table('production_lines')->where('id', $row->line_id)->value('name'), 'date' => substr((string) $row->date, 0, 10), 'status' => __("hrpayroll.lo.req_status.{$status}"), 'by' => $this->caller()->user?->name],
                entityType: 'hr_ot_request', entityId: $row->id, actionUrl: '/hr-payroll/ot-request');
        }

        return ApiResponse::ok(['message' => __("hrpayroll.lo.done.decided.{$status}", ['no' => $row->request_no, 'n' => $people->count() - $removed->count()])]);
    }

    /* ── Helpers ───────────────────────────────────────────────────── */

    /** Employees with their place, for OT Eligibility. */
    private function staff()
    {
        return DB::table('hr_employees as e')->leftJoin('departments as dp', 'dp.id', '=', 'e.department_id')->leftJoin('designations as d', 'd.id', '=', 'e.designation_id')->leftJoin('grades as g', 'g.id', '=', 'e.grade_id')
            ->where('e.company_id', $this->caller()->companyId)->where('e.status', 'ACTIVE')->orderBy('e.employee_code')
            ->select(['e.*', 'dp.name as department', 'd.name as designation', 'g.code as grade']);
    }

    private function employee(string $code): object
    {
        $e = DB::table('hr_employees')->where('company_id', $this->caller()->companyId)->where('employee_code', $code)->first();
        abort_unless($e, 404);

        return $e;
    }

    /** A day's attendance with the employee's place and any OT correction. */
    private function days(string $date)
    {
        return DB::table('hr_attendance_days as a')->join('hr_employees as e', 'e.id', '=', 'a.employee_id')
            ->leftJoin('departments as dp', 'dp.id', '=', 'e.department_id')->leftJoin('designations as d', 'd.id', '=', 'e.designation_id')->leftJoin('grades as g', 'g.id', '=', 'e.grade_id')
            ->leftJoin('hr_shifts as s', 's.id', '=', 'a.shift_id')->leftJoin('hr_ot_corrections as k', fn ($j) => $j->on('k.employee_id', '=', 'a.employee_id')->on('k.date', '=', 'a.date'))
            ->leftJoin('users as u', 'u.id', '=', 'k.updated_by')
            ->where('a.company_id', $this->caller()->companyId)->where('a.date', $date)
            ->select(['a.employee_id', 'a.date', 'a.out_at', 'a.ot_minutes', 'a.ot_type', 'e.*', 'dp.name as department', 'd.name as designation', 'g.code as grade', 's.name as shift', 's.start_time', 's.end_time', 's.working_hours', 's.break_min',
                'k.id as k_id', 'k.hours as k_hours', 'k.rate as k_rate', 'k.reason as k_reason', 'k.updated_at as k_at', 'u.name as k_by']);
    }

    /** OT Correction rows: the attendance OT, its rate and amount, and the correction. */
    private function otRows(Collection $days): Collection
    {
        if ($days->isEmpty()) {
            return collect();
        }
        $book = new OtBook($this->caller()->companyId);
        $people = $days->map(fn ($r) => (object) ['id' => $r->employee_id, 'employment_type_id' => $r->employment_type_id, 'grade_id' => $r->grade_id]);
        $standing = $book->eligibility($people);
        $history = DB::table('hr_ot_correction_history')->whereIn('employee_id', $days->pluck('employee_id'))->where('date', substr((string) $days->first()->date, 0, 10))->pluck('employee_id')->flip();

        return $days->map(function ($r) use ($book, $standing, $history) {
            $hours = round(((int) $r->ot_minutes) / 60, 2);
            $rate = $book->rate((object) ['id' => $r->employee_id, 'gross_a' => $r->gross_a, 'gazette_id' => $r->gazette_id, 'employment_type_id' => $r->employment_type_id], $standing[$r->employee_id]['rule'] ?? null);
            $date = substr((string) $r->date, 0, 10);

            return [
                'key' => "{$r->employee_code}|{$date}", 'date' => $date, 'code' => $r->employee_code, 'name' => $r->name, 'department' => $r->department, 'designation' => $r->designation, 'grade' => $r->grade,
                'ot_type' => $r->ot_type ?? ($standing[$r->employee_id]['type'] ?? null), 'shift' => $r->shift, 'shift_end' => AttendanceBook::regularEnd($r),
                'out_time' => $r->out_at ? CarbonImmutable::parse($r->out_at, 'UTC')->timezone(self::TZ)->format('H:i') : null,
                'original_hours' => $hours, 'original_rate' => $rate, 'original_amount' => round($hours * $rate, 2),
                'corrected_hours' => $r->k_id ? (float) $r->k_hours : null, 'corrected_rate' => $r->k_id ? (float) $r->k_rate : null, 'corrected_amount' => $r->k_id ? round((float) $r->k_hours * (float) $r->k_rate, 2) : null,
                'reason' => $r->k_reason ?? '', 'updated_by' => $r->k_by ?? '', 'updated_at' => $r->k_at ? (string) $r->k_at : '', 'locked' => false, 'has_history' => isset($history[$r->employee_id]),
            ];
        })->values();
    }

    private function history(string $employeeId, string $date, string $action, ?float $hours, ?float $rate, ?string $reason): void
    {
        DB::table('hr_ot_correction_history')->insert(['id' => strtolower((string) Str::ulid()), 'company_id' => $this->caller()->companyId, 'employee_id' => $employeeId, 'date' => $date, 'action' => $action,
            'hours' => $hours, 'rate' => $rate, 'amount' => $hours === null ? null : round($hours * (float) $rate, 2), 'reason' => $reason, 'user_id' => $this->caller()->auditUserId(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function audit(string $subject, ?string $id, string $change, ?string $reason): void
    {
        DB::table('hr_ot_audit')->insert(['id' => strtolower((string) Str::ulid()), 'company_id' => $this->caller()->companyId, 'subject' => $subject, 'subject_id' => $id, 'change' => Str::limit($change, 295), 'reason' => $reason, 'user_id' => $this->caller()->auditUserId(), 'created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * The company's active people whose roles carry OT Approval.
     *
     * @return Collection<int, User>
     */
    private function approvers(): Collection
    {
        $c = $this->caller()->companyId;
        $roles = DB::table('role_has_permissions')->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')->where('permissions.name', 'hrpayroll.ot.approve')->pluck('role_has_permissions.role_id')->all();

        return User::query()->join('company_users', 'company_users.user_id', '=', 'users.id')->where('company_users.company_id', $c)->where('company_users.status', 'ACTIVE')
            ->whereExists(fn ($s) => $s->select(DB::raw(1))->from('user_roles')->whereColumn('user_roles.user_id', 'users.id')->where('user_roles.company_id', $c)->whereIn('user_roles.role_id', $roles ?: [0]))
            ->orderBy('users.name')->get(['users.*'])->toBase();
    }

    /** Each employee's shift on a day: the newest roster covering it, else the default shift. */
    private function shiftsOn(array $ids, string $date): array
    {
        $c = $this->caller()->companyId;
        $rosters = DB::table('hr_shift_rosters')->whereIn('employee_id', $ids)->where('from_date', '<=', $date)->where(fn ($q) => $q->whereNull('to_date')->orWhere('to_date', '>=', $date))
            ->orderByDesc('created_at')->orderByDesc('id')->get(['employee_id', 'shift_id'])->unique('employee_id')->pluck('shift_id', 'employee_id');
        $code = DB::table('hr_payroll_settings')->where('company_id', $c)->where('key', 'DEFAULT_SHIFT')->value('value') ?? HrDefaults::DEFAULT_SHIFT;
        $default = DB::table('hr_shifts')->where('company_id', $c)->where('active', true)->where('code', $code)->value('id') ?? DB::table('hr_shifts')->where('company_id', $c)->where('active', true)->orderBy('start_time')->value('id');

        return collect($ids)->mapWithKeys(fn ($id) => [$id => $rosters[$id] ?? $default])->all();
    }

    /** @return array{0: array<string, mixed>, 1: Collection<int, object>, 2: int} */
    private function validRequest(Request $request, ?string $except): array
    {
        $c = $this->caller()->companyId;
        $d = $request->validate([
            'date' => ['required', 'date'],
            'department_id' => ['required', Rule::exists('departments', 'id')->where('company_id', $c)],
            'section_id' => ['required', Rule::exists('sections', 'id')->where('company_id', $c)],
            'line_id' => ['required', Rule::exists('production_lines', 'id')->where('company_id', $c)],
            'shift_id' => ['nullable', Rule::exists('hr_shifts', 'id')->where('company_id', $c)],
            'ot_start' => ['required', 'date_format:H:i'], 'ot_end' => ['required', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:1000'],
            'employees' => ['required', 'array', 'min:1'], 'employees.*' => ['string'],
            'approval' => ['required', Rule::in(['DIRECT', 'ROUTED'])],
            'approver_id' => [Rule::requiredIf(fn () => $request->input('approval') === 'ROUTED'), 'nullable', 'string'],
        ], ['employees.required' => __('hrpayroll.lo.v.employees'), 'employees.min' => __('hrpayroll.lo.v.employees'), 'approver_id.required' => __('hrpayroll.lo.v.approver'), 'reason.required' => __('hrpayroll.lo.v.reason')]);
        $line = DB::table('production_lines')->where('id', $d['line_id'])->first();
        $section = DB::table('sections')->where('id', $d['section_id'])->first();
        if ($section->department_id !== $d['department_id'] || ($line->section_id ? $line->section_id !== $d['section_id'] : $line->department_id !== $d['department_id'])) {
            throw ValidationException::withMessages(['line_id' => __('hrpayroll.lo.v.place')]);
        }
        $minutes = (int) ((strtotime("2000-01-01 {$d['ot_end']}") - strtotime("2000-01-01 {$d['ot_start']}")) / 60);
        $minutes = $minutes <= 0 ? $minutes + 1440 : $minutes;
        if ($minutes > 12 * 60) {
            throw ValidationException::withMessages(['ot_end' => __('hrpayroll.lo.v.window')]);
        }
        if ($d['approval'] === 'DIRECT' && ! $this->caller()->can('hrpayroll.ot.approve')) {
            throw ValidationException::withMessages(['approval' => __('hrpayroll.lo.v.direct')]);
        }
        if ($d['approval'] === 'ROUTED' && ! $this->approvers()->contains('id', $d['approver_id'])) {
            throw ValidationException::withMessages(['approver_id' => __('hrpayroll.lo.v.approver')]);
        }
        $employees = DB::table('hr_employees')->where('company_id', $c)->where('line_id', $d['line_id'])->where('status', 'ACTIVE')->whereIn('employee_code', $d['employees'])->get(['id', 'employee_code']);
        if ($employees->count() !== count(array_unique($d['employees']))) {
            throw ValidationException::withMessages(['employees' => __('hrpayroll.lo.v.not_on_line')]);
        }
        $date = CarbonImmutable::parse($d['date'])->toDateString();
        $taken = DB::table('hr_ot_request_employees as x')->join('hr_ot_requests as r', 'r.id', '=', 'x.request_id')->join('hr_employees as e', 'e.id', '=', 'x.employee_id')
            ->where('r.company_id', $c)->where('r.date', $date)->whereIn('r.status', ['PENDING', 'APPROVED'])->where('x.removed', false)->whereIn('x.employee_id', $employees->pluck('id'))
            ->when($except, fn ($q, $id) => $q->where('r.id', '!=', $id))->orderBy('e.employee_code')->first(['e.employee_code', 'r.request_no']);
        if ($taken) {
            throw ValidationException::withMessages(['employees' => __('hrpayroll.lo.v.already', ['code' => $taken->employee_code, 'no' => $taken->request_no])]);
        }

        return [['date' => $date] + $d, $employees, $minutes];
    }

    private function requestValues(array $d, int $minutes, bool $direct): array
    {
        return [
            'date' => $d['date'], 'department_id' => $d['department_id'], 'section_id' => $d['section_id'], 'line_id' => $d['line_id'], 'shift_id' => $d['shift_id'] ?? null,
            'ot_start' => $d['ot_start'], 'ot_end' => $d['ot_end'], 'minutes' => $minutes, 'reason' => $d['reason'], 'approval' => $direct ? 'DIRECT' : 'ROUTED',
            'approver_id' => $direct ? null : $d['approver_id'], 'status' => $direct ? 'APPROVED' : 'PENDING',
            'decided_by' => $direct ? $this->caller()->auditUserId() : null, 'decided_at' => $direct ? now() : null, 'updated_at' => now(),
        ];
    }

    private function afterSubmit(string $id, bool $direct, string $date, Collection $employees): void
    {
        if ($direct) {
            $this->rework($date, $employees->pluck('id')->all());

            return;
        }
        $r = DB::table('hr_ot_requests')->where('id', $id)->first();
        $approver = User::query()->find($r->approver_id);
        if ($approver && $approver->id !== $this->caller()->user?->id) {
            app(NotificationDispatcher::class)->send($approver, 'HR_OT_REQUESTED', ['no' => $r->request_no, 'line' => DB::table('production_lines')->where('id', $r->line_id)->value('name'), 'date' => $date, 'window' => "{$r->ot_start}–{$r->ot_end}", 'n' => $employees->count()],
                entityType: 'hr_ot_request', entityId: $r->id, actionUrl: '/hr-payroll/ot-approval');
        }
    }

    private function nextRequestNo(string $c): string
    {
        $last = DB::table('hr_ot_requests')->where('company_id', $c)->where('request_no', 'like', 'OTR-%')->orderByDesc('request_no')->value('request_no');

        return 'OTR-'.str_pad((string) ((int) substr((string) $last, 4) + 1), 6, '0', STR_PAD_LEFT);
    }

    private function requestQuery()
    {
        return DB::table('hr_ot_requests as r')->leftJoin('departments as dp', 'dp.id', '=', 'r.department_id')->leftJoin('sections as s', 's.id', '=', 'r.section_id')
            ->leftJoin('production_lines as l', 'l.id', '=', 'r.line_id')->leftJoin('hr_shifts as sh', 'sh.id', '=', 'r.shift_id')
            ->leftJoin('users as rq', 'rq.id', '=', 'r.requested_by')->leftJoin('users as ap', 'ap.id', '=', 'r.approver_id')->leftJoin('users as dc', 'dc.id', '=', 'r.decided_by')
            ->where('r.company_id', $this->caller()->companyId)->orderByDesc('r.created_at')->orderByDesc('r.request_no')
            ->select(['r.*', 'dp.name as department', 's.name as section', 'l.name as line', 'sh.name as shift_name', 'sh.start_time as shift_start', 'sh.end_time as shift_end', 'rq.name as requested_by_name', 'ap.name as approver', 'dc.name as decided_by_name']);
    }

    /**
     * Requests with their employees: approved hours (the window), the day's
     * actual OT past the shift end, the payable hours (the attendance OT, or
     * its correction), the rate and the amount.
     *
     * @return list<array<string, mixed>>
     */
    private function requestRows(Collection $rows): array
    {
        if ($rows->isEmpty()) {
            return [];
        }
        $c = $this->caller()->companyId;
        $people = DB::table('hr_ot_request_employees as x')->join('hr_employees as e', 'e.id', '=', 'x.employee_id')->leftJoin('designations as d', 'd.id', '=', 'e.designation_id')
            ->whereIn('x.request_id', $rows->pluck('id'))->orderBy('e.employee_code')->get(['e.*', 'x.id as entry_id', 'x.request_id', 'x.removed', 'd.name as designation'])->groupBy('request_id');
        $days = DB::table('hr_attendance_days as a')->leftJoin('hr_shifts as s', 's.id', '=', 'a.shift_id')->leftJoin('hr_ot_corrections as k', fn ($j) => $j->on('k.employee_id', '=', 'a.employee_id')->on('k.date', '=', 'a.date'))
            ->where('a.company_id', $c)->whereIn('a.date', $rows->pluck('date')->unique()->all())->whereIn('a.employee_id', $people->flatten(1)->pluck('id')->unique()->all())
            ->get(['a.employee_id', 'a.date', 'a.out_at', 'a.ot_minutes', 's.start_time', 's.end_time', 's.working_hours', 's.break_min', 'k.hours as k_hours', 'k.rate as k_rate'])->keyBy(fn ($a) => $a->employee_id.'|'.substr((string) $a->date, 0, 10));
        $book = new OtBook($c);
        $standing = $book->eligibility($people->flatten(1));

        return $rows->map(function ($r) use ($people, $days, $book, $standing) {
            $date = substr((string) $r->date, 0, 10);
            $employees = ($people[$r->id] ?? collect())->map(function ($e) use ($r, $date, $days, $book, $standing) {
                $a = $days["{$e->id}|{$date}"] ?? null;
                $actual = null;
                if ($a?->out_at && $a->end_time) {
                    $end = CarbonImmutable::parse($date.' '.AttendanceBook::regularEnd($a), self::TZ);
                    // A night's regular end is the next morning's.
                    $end = $end->format('H:i') <= $a->start_time ? $end->addDay() : $end;
                    $out = CarbonImmutable::parse($a->out_at, 'UTC')->timezone(self::TZ);
                    $actual = round(max(0, (int) $end->diffInMinutes($out, false)) / 60, 2);
                }
                $payable = $a === null ? null : ($a->k_hours !== null ? (float) $a->k_hours : round(((int) $a->ot_minutes) / 60, 2));
                $rate = $a?->k_rate !== null ? (float) $a->k_rate : $book->rate($e, $standing[$e->id]['rule'] ?? null);

                return ['entry_id' => $e->entry_id, 'code' => $e->employee_code, 'name' => $e->name, 'designation' => $e->designation, 'removed' => (bool) $e->removed,
                    'approved_hours' => $e->removed ? 0 : round($r->minutes / 60, 2), 'actual_hours' => $actual, 'payable_hours' => $payable, 'rate' => $rate, 'amount' => $payable === null ? null : round($payable * $rate, 2)];
            })->values();
            $kept = $employees->where('removed', false)->count();

            return [
                'id' => $r->id, 'request_no' => $r->request_no, 'date' => $date, 'department_id' => $r->department_id, 'section_id' => $r->section_id, 'line_id' => $r->line_id, 'shift_id' => $r->shift_id,
                'department' => $r->department, 'section' => $r->section, 'line' => $r->line, 'shift' => $r->shift_name ? $r->shift_name.' ('.Clock::range($r->shift_start, $r->shift_end).')' : null,
                'ot_start' => $r->ot_start, 'ot_end' => $r->ot_end, 'hours' => round($r->minutes / 60, 2), 'requested_hours' => round($r->minutes / 60 * $kept, 2),
                'reason' => $r->reason, 'approval' => $r->approval, 'approver_id' => $r->approver_id, 'approver' => $r->approver, 'status' => $r->status,
                'decision_note' => $r->decision_note, 'decided_by' => $r->decided_by_name, 'decided_at' => $r->decided_at ? (string) $r->decided_at : null,
                'requested_by' => $r->requested_by_name, 'requested_at' => (string) $r->created_at, 'employee_count' => $kept, 'employees' => $employees->all(),
            ];
        })->values()->all();
    }

    private function rework(string $date, array $ids): void
    {
        if ($date <= CarbonImmutable::now(self::TZ)->toDateString()) {
            (new AttendanceBook($this->caller()->companyId))->process($date, $date, $ids);
        }
    }
}
