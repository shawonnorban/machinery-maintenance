<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Http\Controllers\Api;

use App\Modules\HrPayroll\Support\AttendanceBook;
use App\Modules\HrPayroll\Support\LeaveBook;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * HR & Payroll → Leave (AnnotechERP 24/05), as Brickland lays it out: the
 * Records (leave applications — ref no, applicant, type, mode, from, to,
 * days; deleted, not edited), Leave Status (an employee's balance of each
 * leave type) and Save application — keyed by HR, so it may be saved
 * already approved; approving takes from the balance at once.
 *
 * A daily leave runs from a date to a date; an early departure is a date, a
 * time and the hours. An approved leave is worked into the attendance days.
 */
class HrLeaveApiController extends ApiController
{
    public const STATUSES = ['PENDING', 'APPROVED', 'REJECTED', 'CANCELLED'];

    private const TZ = 'Asia/Dhaka';

    public function options(): JsonResponse
    {
        $this->allowEither('hrpayroll.leave.view', 'hrpayroll.leave.manage');
        $c = $this->caller()->companyId;
        $book = new LeaveBook($c);
        $today = CarbonImmutable::now(self::TZ)->toDateString();

        return ApiResponse::ok([
            'today' => $today,
            'company' => DB::table('companies')->where('id', $c)->value('name'),
            'fiscal_years' => DB::table('acc_fiscal_years')->where('company_id', $c)->orderByDesc('cal_start')->get(['id', 'name'])->map(fn ($y) => ['value' => $y->id, 'label' => $y->name])->all(),
            'fiscal_year' => $book->yearOf($today)?->id,
            'employees' => $this->employees()->map(fn ($e) => ['value' => $e->employee_code, 'label' => "{$e->employee_code} — {$e->name}", 'name' => $e->name, 'summary' => $this->summary($e), 'designation' => $e->designation, 'gender' => $e->gender])->all(),
            'leave_types' => $book->types()->map(fn ($t) => ['value' => $t->id, 'label' => "{$t->name} ({$t->code})", 'applicable_for' => $t->applicable_for, 'balance_check' => (bool) $t->balance_check])->all(),
            'statuses' => self::STATUSES,
        ]);
    }

    /** Records: the leave applications, newest first. */
    public function index(): JsonResponse
    {
        $this->allowEither('hrpayroll.leave.view', 'hrpayroll.leave.manage');
        $rows = DB::table('hr_leave_applications as a')->join('hr_employees as e', 'e.id', '=', 'a.employee_id')->join('hr_leave_types as t', 't.id', '=', 'a.leave_type_id')
            ->where('a.company_id', $this->caller()->companyId)->orderByDesc('a.created_at')->orderByDesc('a.ref_no')->limit(1000)
            ->get(['a.ref_no', 'a.mode', 'a.from_date', 'a.to_date', 'a.days', 'a.hours', 'a.early_time', 'a.status', 'a.apply_date', 'a.reason', 'e.employee_code', 'e.name', 't.name as type_name', 't.code as type_code']);

        return ApiResponse::ok($rows->map(fn ($r) => [
            'ref_no' => $r->ref_no, 'applicant' => "{$r->name} ({$r->employee_code})", 'employee' => $r->employee_code, 'leave_type' => "{$r->type_name} ({$r->type_code})",
            'mode' => $r->mode, 'from' => substr((string) $r->from_date, 0, 10), 'to' => substr((string) $r->to_date, 0, 10),
            'days' => $r->mode === 'DAILY' ? (float) $r->days : null, 'hours' => $r->mode === 'EARLY_DEPARTURE' ? (float) $r->hours : null, 'early_time' => $r->early_time,
            'status' => $r->status, 'apply_date' => substr((string) $r->apply_date, 0, 10), 'reason' => $r->reason,
        ])->values()->all());
    }

    /** Leave Status: an employee's balance of each leave type in a year. */
    public function balances(Request $request): JsonResponse
    {
        $this->allowEither('hrpayroll.leave.view', 'hrpayroll.leave.manage');
        $c = $this->caller()->companyId;
        $e = $this->employee((string) $request->query('employee'));
        $book = new LeaveBook($c);
        $year = $request->query('fiscal_year') ? DB::table('acc_fiscal_years')->where('company_id', $c)->where('id', $request->query('fiscal_year'))->first() : $book->yearOf(CarbonImmutable::now(self::TZ)->toDateString());
        abort_unless($year, 404);

        return ApiResponse::ok(['employee' => ['code' => $e->employee_code, 'name' => $e->name, 'summary' => $this->summary($e)], 'year' => $year->name, 'rows' => $book->balances($e, $year)]);
    }

    /** Save application. */
    public function store(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.leave.manage');
        $c = $this->caller()->companyId;
        $early = $request->input('mode') === 'EARLY_DEPARTURE';
        $d = $request->validate([
            'fiscal_year_id' => ['required', Rule::exists('acc_fiscal_years', 'id')->where('company_id', $c)],
            'mode' => ['required', Rule::in(['DAILY', 'EARLY_DEPARTURE'])],
            'employee' => ['required', 'string'],
            'purpose' => ['nullable', 'string', 'max:120'],
            'leave_type_id' => ['required', Rule::exists('hr_leave_types', 'id')->where('company_id', $c)->where('active', true)],
            'from_date' => [$early ? 'nullable' : 'required', 'date'],
            'to_date' => [$early ? 'nullable' : 'required', 'date', 'after_or_equal:from_date'],
            'early_date' => [$early ? 'required' : 'nullable', 'date'],
            'early_time' => [$early ? 'required' : 'nullable', 'date_format:H:i'],
            'hours' => [$early ? 'required' : 'nullable', 'numeric', 'gt:0', 'max:'.LeaveBook::HOURS_PER_DAY],
            'duty_by' => ['nullable', 'string'],
            'joining_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'confirmed' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'reason_bn' => ['nullable', 'string', 'max:2000'],
        ], [
            'employee.required' => __('hrpayroll.lo.v.employee'), 'leave_type_id.required' => __('hrpayroll.lo.v.leave_type'),
            'to_date.after_or_equal' => __('hrpayroll.lo.v.to_before_from'), 'early_time.date_format' => __('hrpayroll.lo.v.time'),
        ]);
        $e = $this->employee($d['employee']);
        $type = DB::table('hr_leave_types')->where('id', $d['leave_type_id'])->first();
        $year = DB::table('acc_fiscal_years')->where('id', $d['fiscal_year_id'])->first();
        $from = $early ? $d['early_date'] : $d['from_date'];
        $to = $early ? $d['early_date'] : $d['to_date'];
        if ($type->applicable_for !== 'COMMON' && $e->gender && strtoupper((string) $e->gender) !== $type->applicable_for) {
            throw ValidationException::withMessages(['leave_type_id' => __('hrpayroll.lo.v.gender', ['type' => $type->name])]);
        }
        if ($from < substr((string) $year->cal_start, 0, 10) || $to > substr((string) $year->cal_end, 0, 10)) {
            throw ValidationException::withMessages([$early ? 'early_date' : 'from_date' => __('hrpayroll.lo.v.outside_year', ['year' => $year->name])]);
        }
        $duty = ! empty($d['duty_by']) ? $this->employee($d['duty_by']) : null;
        if ($duty && $duty->id === $e->id) {
            throw ValidationException::withMessages(['duty_by' => __('hrpayroll.lo.v.duty_self')]);
        }
        $clash = DB::table('hr_leave_applications')->where('employee_id', $e->id)->whereIn('status', ['PENDING', 'APPROVED'])->where('mode', $d['mode'])
            ->where('from_date', '<=', $to)->where('to_date', '>=', $from)->value('ref_no');
        if ($clash) {
            throw ValidationException::withMessages([$early ? 'early_date' : 'from_date' => __('hrpayroll.lo.v.overlap', ['ref' => $clash])]);
        }
        $days = $early ? 0 : (float) (CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) + 1);
        if ($d['status'] === 'APPROVED' && $type->balance_check) {
            $left = collect((new LeaveBook($c))->balances($e, $year))->firstWhere('leave_type_id', $type->id)['balance_day'] ?? null;
            $want = $early ? (float) $d['hours'] / LeaveBook::HOURS_PER_DAY : $days;
            if ($left !== null && $want > $left) {
                throw ValidationException::withMessages([$early ? 'hours' : 'to_date' => __('hrpayroll.lo.v.balance', ['left' => $left])]);
            }
        }
        $today = CarbonImmutable::now(self::TZ)->toDateString();
        $ref = $this->nextRef($c);
        DB::table('hr_leave_applications')->insert([
            'id' => strtolower((string) Str::ulid()), 'company_id' => $c, 'ref_no' => $ref, 'fiscal_year_id' => $year->id, 'mode' => $d['mode'],
            'employee_id' => $e->id, 'leave_type_id' => $type->id, 'purpose' => $early ? ($d['purpose'] ?? null) : null,
            'from_date' => $from, 'to_date' => $to, 'days' => $days, 'early_time' => $early ? $d['early_time'] : null, 'hours' => $early ? (float) $d['hours'] : null,
            'apply_date' => $today, 'approve_date' => $d['status'] === 'APPROVED' ? $today : null, 'duty_by_id' => $duty?->id,
            'joining_date' => $early ? null : ($d['joining_date'] ?? CarbonImmutable::parse($to)->addDay()->toDateString()),
            'status' => $d['status'], 'confirmed' => (bool) ($d['confirmed'] ?? false), 'reason' => $d['reason'] ?? null, 'reason_bn' => $d['reason_bn'] ?? null,
            'created_by' => $this->caller()->auditUserId(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->rework($e->id, $from, $to);

        return ApiResponse::created(['ref_no' => $ref, 'message' => __('hrpayroll.lo.done.saved', ['ref' => $ref])]);
    }

    public function destroy(string $ref): JsonResponse
    {
        $this->allow('hrpayroll.leave.manage');
        $row = DB::table('hr_leave_applications')->where('company_id', $this->caller()->companyId)->where('ref_no', $ref)->first();
        abort_unless($row, 404);
        DB::table('hr_leave_applications')->where('id', $row->id)->delete();
        $this->rework($row->employee_id, substr((string) $row->from_date, 0, 10), substr((string) $row->to_date, 0, 10));

        return ApiResponse::ok(['message' => __('hrpayroll.lo.done.deleted', ['ref' => $ref])]);
    }

    /* ── Helpers ───────────────────────────────────────────────────── */

    private function employees()
    {
        return DB::table('hr_employees as e')->leftJoin('designations as d', 'd.id', '=', 'e.designation_id')->leftJoin('sections as s', 's.id', '=', 'e.section_id')
            ->where('e.company_id', $this->caller()->companyId)->whereIn('e.status', ['ACTIVE', 'SUSPENDED', 'INACTIVE'])->orderBy('e.employee_code')
            ->get(['e.id', 'e.employee_code', 'e.name', 'e.gender', 'e.joining_date', 'd.name as designation', 's.name as section']);
    }

    private function employee(string $code): object
    {
        $e = DB::table('hr_employees as e')->leftJoin('designations as d', 'd.id', '=', 'e.designation_id')->leftJoin('sections as s', 's.id', '=', 'e.section_id')
            ->where('e.company_id', $this->caller()->companyId)->where('e.employee_code', $code)->first(['e.*', 'd.name as designation', 's.name as section']);
        if (! $e) {
            throw ValidationException::withMessages(['employee' => __('hrpayroll.lo.v.employee')]);
        }

        return $e;
    }

    /** "Md. Manik Raton, Asst. Officer, Store" */
    private function summary(object $e): string
    {
        return implode(', ', array_filter([$e->name, $e->designation ?? null, $e->section ?? null]));
    }

    private function nextRef(string $c): string
    {
        $last = DB::table('hr_leave_applications')->where('company_id', $c)->where('ref_no', 'like', 'LV-%')->orderByDesc('ref_no')->value('ref_no');

        return 'LV-'.str_pad((string) ((int) substr((string) $last, 3) + 1), 6, '0', STR_PAD_LEFT);
    }

    /** The leave's days worked out again on the attendance (never past today). */
    private function rework(string $employeeId, string $from, string $to): void
    {
        if ($from <= CarbonImmutable::now(self::TZ)->toDateString()) {
            (new AttendanceBook($this->caller()->companyId))->process($from, $to, [$employeeId]);
        }
    }
}
