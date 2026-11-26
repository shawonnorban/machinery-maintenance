<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Support;

use App\Shared\Support\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * HR & Payroll → Reports (AnnotechERP 24/02): Brickland's 47 reports in six
 * categories, each a set of filters, stat tiles, columns, rows and a total
 * row, worked out from the records the other HR screens keep — the employee
 * roll and postings, attendance days, OT (with corrections), daily salary,
 * the pay period's slips, bonus lines, leave, movement and settlements.
 *
 * A report answers `run()` as:
 *   tiles   [{label, value, kind}]            the stat cards above the grid
 *   columns [{key, label, kind}]              kind: text, int, num, money, hours, pct, date
 *   rows    [{key => value}]                  raw values; the screen formats them
 *   foot    {key => value}|null               the total row
 *   empty   string                            what to say when nothing matched
 *   note    string|null                       the line under the grid
 */
class HrReportBook
{
    public const TZ = 'Asia/Dhaka';

    public const MAX_ROWS = 5000;

    public const CATEGORIES = ['employee', 'manpower', 'attendance', 'ot', 'salary', 'hr'];

    private const DIMS = ['department', 'section', 'line', 'floor', 'designation'];

    public const MANPOWER = ['OPERATOR', 'QUALITY', 'HELPER', 'SUPERVISOR', 'STAFF'];

    private const EMP_STATUS = ['ACTIVE', 'INACTIVE', 'SUSPENDED', 'SEPARATED'];

    private const APPROVAL = ['PENDING', 'APPROVED', 'REJECTED'];

    /**
     * [no, key, category, filters, [status label, choices]|null, wide] in Brickland's order.
     * Numbers 22–24 are not on Brickland's list.
     */
    public const REPORTS = [
        [1, 'employee-master', 'employee', ['department', 'section', 'line', 'floor', 'designation', 'employee', 'status'], ['employee_status', self::EMP_STATUS], true],
        [2, 'employee-list', 'employee', ['department', 'section', 'line', 'floor', 'designation', 'employee', 'status'], ['employee_status', self::EMP_STATUS], false],
        [3, 'employee-assignment', 'employee', ['department', 'section', 'line', 'floor', 'designation', 'employee', 'status'], ['assignment_status', ['PENDING', 'ASSIGNED']], true],
        [4, 'joining', 'employee', ['from', 'to', 'department', 'section', 'line', 'floor', 'designation', 'employee'], null, false],
        [5, 'employee-status', 'employee', ['department', 'section', 'line', 'floor', 'designation', 'employee', 'status'], ['employee_status', self::EMP_STATUS], false],
        [6, 'employee-movement', 'employee', ['from', 'to', 'department', 'section', 'line', 'floor', 'designation', 'employee', 'status'], ['movement', ['TRANSFER', 'PROMOTION', 'INCREMENT', 'REJOIN', 'SEPARATION']], true],
        [7, 'total-manpower', 'manpower', ['date', 'department', 'section', 'line', 'floor', 'designation'], null, false],
        [8, 'designation-manpower', 'manpower', ['date', 'department', 'section', 'line', 'floor', 'designation'], null, false],
        [9, 'floor-manpower', 'manpower', ['date', 'department', 'section', 'line', 'floor', 'designation'], null, true],
        [10, 'department-manpower', 'manpower', ['date', 'department', 'section', 'line', 'floor', 'designation'], null, true],
        [11, 'section-manpower', 'manpower', ['date', 'department', 'section', 'line', 'floor', 'designation'], null, true],
        [12, 'line-manpower', 'manpower', ['date', 'department', 'section', 'line', 'floor', 'designation'], null, true],
        [13, 'daily-attendance', 'attendance', ['date', 'department', 'section', 'line', 'floor', 'designation', 'shift', 'employee', 'status'], ['attendance_status', ['PRESENT', 'LATE', 'EARLY_OUT', 'ABSENT', 'LEAVE', 'MISSING_PUNCH', 'HOLIDAY', 'WEEKLY_OFF']], true],
        [14, 'monthly-attendance', 'attendance', ['month', 'department', 'section', 'line', 'floor', 'designation', 'employee'], null, true],
        [15, 'present', 'attendance', ['date', 'department', 'section', 'line', 'floor', 'designation', 'shift', 'employee'], null, false],
        [16, 'absent', 'attendance', ['date', 'department', 'section', 'line', 'floor', 'designation', 'shift', 'employee'], null, false],
        [17, 'late', 'attendance', ['from', 'to', 'department', 'section', 'line', 'floor', 'designation', 'shift', 'employee'], null, false],
        [18, 'early-out', 'attendance', ['from', 'to', 'department', 'section', 'line', 'floor', 'designation', 'shift', 'employee'], null, false],
        [19, 'missing-punch', 'attendance', ['from', 'to', 'department', 'section', 'line', 'floor', 'designation', 'shift', 'employee'], null, false],
        [20, 'shift-attendance', 'attendance', ['date', 'department', 'section', 'line', 'floor', 'designation', 'shift'], null, true],
        [21, 'attendance-correction', 'attendance', ['from', 'to', 'department', 'section', 'line', 'floor', 'designation', 'employee', 'status'], ['correction_status', self::APPROVAL], true],
        [25, 'daily-ot', 'ot', ['date', 'department', 'section', 'line', 'floor', 'designation', 'grade', 'otType', 'employee'], null, true],
        [26, 'employee-ot', 'ot', ['from', 'to', 'department', 'section', 'line', 'floor', 'designation', 'grade', 'otType', 'employee'], null, false],
        [27, 'section-ot', 'ot', ['from', 'to', 'department', 'section', 'line'], null, false],
        [28, 'line-ot', 'ot', ['from', 'to', 'department', 'section', 'line'], null, false],
        [29, 'ot-salary-summary', 'ot', ['from', 'to', 'department', 'section', 'line'], null, false],
        [44, 'monthly-ot', 'ot', ['month', 'department', 'section', 'line', 'floor', 'designation', 'grade', 'otType', 'employee'], null, true],
        [45, 'ot-correction-history', 'ot', ['from', 'to', 'department', 'section', 'line', 'floor', 'designation', 'employee'], null, true],
        [46, 'grade-ot', 'ot', ['from', 'to', 'department', 'section', 'line', 'floor', 'designation', 'grade', 'otType', 'employee'], null, false],
        [30, 'daily-salary', 'salary', ['date', 'department', 'section', 'line', 'floor', 'designation', 'employee'], null, true],
        [31, 'employee-daily-salary', 'salary', ['from', 'to', 'department', 'section', 'line', 'floor', 'designation', 'employee'], null, true],
        [32, 'monthly-salary', 'salary', ['period', 'department', 'section', 'line', 'floor', 'designation', 'employee'], null, true],
        [33, 'salary-register', 'salary', ['period', 'department', 'section', 'line', 'floor', 'designation', 'employee'], null, true],
        [34, 'payslip', 'salary', ['period', 'employee'], null, false],
        [35, 'salary-deduction', 'salary', ['period', 'department', 'section', 'line', 'floor', 'designation', 'employee'], null, true],
        [36, 'allowance', 'salary', ['period', 'department', 'section', 'line', 'floor', 'designation', 'employee'], null, false],
        [37, 'bonus', 'salary', ['festival', 'period', 'department', 'section', 'line', 'floor', 'designation', 'employee'], null, true],
        [38, 'bank-salary-sheet', 'salary', ['period', 'department', 'section', 'line', 'floor', 'designation', 'employee', 'status'], ['payment_mode', ['BANK', 'CASH']], false],
        [47, 'gross-a-salary', 'salary', ['period', 'department', 'section', 'grade', 'structure', 'employee', 'status'], ['payroll_status', ['CALCULATED', 'REVIEWED', 'APPROVED', 'FINALIZED', 'PAID', 'ADJUSTMENT_REQUIRED']], true],
        [48, 'gross-b-salary', 'salary', ['period', 'department', 'section', 'grade', 'structure', 'employee', 'status'], ['complex_status', ['DRAFT', 'PENDING_APPROVAL', 'APPROVED', 'LOCKED', 'REOPENED']], true],
        [49, 'gross-ab-combined', 'salary', ['period', 'department', 'section', 'grade', 'structure', 'employee', 'status'], ['payroll_status', ['CALCULATED', 'REVIEWED', 'APPROVED', 'FINALIZED', 'PAID', 'ADJUSTMENT_REQUIRED']], true],
        [50, 'payroll-adjustment', 'salary', ['from', 'to', 'department', 'section', 'line', 'floor', 'designation', 'employee', 'status'], ['adjustment_status', ['PENDING', 'APPROVED', 'REJECTED', 'APPLIED']], true],
        [39, 'leave', 'hr', ['from', 'to', 'department', 'section', 'line', 'floor', 'designation', 'employee', 'status'], ['leave_status', ['PENDING', 'APPROVED', 'REJECTED', 'CANCELLED']], true],
        [40, 'leave-balance', 'hr', ['fiscalYear', 'department', 'section', 'line', 'floor', 'designation', 'employee'], null, false],
        [41, 'promotion-increment', 'hr', ['from', 'to', 'department', 'section', 'line', 'floor', 'designation', 'employee', 'status'], ['approval_status', self::APPROVAL], true],
        [42, 'transfer', 'hr', ['from', 'to', 'department', 'section', 'line', 'floor', 'designation', 'employee', 'status'], ['transfer_status', ['DRAFT', 'FINALIZED']], true],
        [43, 'final-settlement', 'hr', ['from', 'to', 'department', 'section', 'line', 'floor', 'designation', 'employee', 'status'], ['settlement_status', ['DRAFT', 'APPROVED', 'PAID']], true],
    ];

    /** @var array<string, string>|null designation id => manpower category */
    private ?array $categoryMap = null;

    private ?SalaryBook $salaryBook = null;

    public function __construct(private readonly string $companyId) {}

    /** @return array<string, mixed>|null the definition of a report key */
    public static function find(string $key): ?array
    {
        foreach (self::REPORTS as [$no, $k, $cat, $filters, $status, $wide]) {
            if ($k === $key) {
                return compact('no', 'cat', 'filters', 'status', 'wide') + ['key' => $k];
            }
        }

        return null;
    }

    /* ── Catalog ───────────────────────────────────────────────────── */

    /** The categories, the reports and their filter choices, for the screen. @return array<string, mixed> */
    public function catalog(): array
    {
        $w = fn (string $k, array $r = []) => __("hrpayroll.rp.{$k}", $r);

        return [
            'categories' => array_map(fn ($c) => ['key' => $c, 'name' => $w("cat.{$c}"), 'description' => $w("cat_desc.{$c}")], self::CATEGORIES),
            'reports' => array_map(fn ($r) => [
                'no' => $r[0], 'key' => $r[1], 'category' => $r[2], 'name' => $w("r.{$r[1]}"), 'description' => $w("rd.{$r[1]}"), 'filters' => $r[3],
                'statusLabel' => $r[4] ? $w("st_label.{$r[4][0]}") : null,
                'statusChoices' => $r[4] ? array_map(fn ($v) => ['value' => $v, 'label' => $w("s.{$v}")], $r[4][1]) : null,
                'wide' => $r[5], 'required' => $r[1] === 'payslip' ? ['period', 'employee'] : [],
            ], self::REPORTS),
            'options' => $this->options(),
            'defaults' => $this->defaults(),
        ];
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function options(): array
    {
        $c = $this->companyId;
        $pick = fn (string $table, array $extra = []) => DB::table($table)->where('company_id', $c)->orderBy('name')->get(array_merge(['id', 'name'], $extra));

        return [
            'departments' => $pick('departments')->map(fn ($x) => ['value' => $x->id, 'label' => $x->name])->values()->all(),
            'sections' => $pick('sections', ['department_id'])->map(fn ($x) => ['value' => $x->id, 'label' => $x->name, 'departmentId' => $x->department_id])->values()->all(),
            'lines' => DB::table('production_lines')->where('company_id', $c)->orderBy('code')->get(['id', 'code', 'name', 'department_id', 'section_id'])
                ->map(fn ($x) => ['value' => $x->id, 'label' => self::lineLabel($x->code, $x->name), 'departmentId' => $x->department_id, 'sectionId' => $x->section_id])->values()->all(),
            'floors' => $pick('floors')->map(fn ($x) => ['value' => $x->id, 'label' => $x->name])->values()->all(),
            'designations' => $pick('designations')->map(fn ($x) => ['value' => $x->id, 'label' => $x->name])->values()->all(),
            'shifts' => DB::table('hr_shifts')->where('company_id', $c)->orderBy('start_time')->get(['id', 'name', 'start_time', 'end_time'])
                ->map(fn ($x) => ['value' => $x->id, 'label' => $x->name.' ('.Clock::range(substr((string) $x->start_time, 0, 5), substr((string) $x->end_time, 0, 5)).')'])->values()->all(),
            'grades' => DB::table('grades')->where('company_id', $c)->orderBy('sort_order')->orderBy('code')->get(['id', 'name', 'code'])->map(fn ($x) => ['value' => $x->id, 'label' => $x->name ?: $x->code])->values()->all(),
            'otTypes' => [['value' => 'GENERAL', 'label' => __('hrpayroll.rp.s.GENERAL')], ['value' => 'WORKER', 'label' => __('hrpayroll.rp.s.WORKER')]],
            'structures' => DB::table('hr_salary_slips')->where('company_id', $c)->whereNotNull('structure')->distinct()->orderBy('structure')->pluck('structure')->map(fn ($s) => ['value' => $s, 'label' => $s])->values()->all(),
            'periods' => DB::table('hr_pay_periods')->where('company_id', $c)->orderByDesc('start_date')->limit(36)->get(['id', 'period_no', 'start_date', 'end_date'])
                ->map(fn ($p) => ['value' => $p->id, 'label' => $p->period_no.' ('.self::dmy($p->start_date).' – '.self::dmy($p->end_date).')'])->values()->all(),
            'fiscalYears' => DB::table('acc_fiscal_years')->where('company_id', $c)->orderByDesc('cal_start')->get(['id', 'name'])->map(fn ($y) => ['value' => $y->id, 'label' => $y->name])->values()->all(),
            'festivals' => DB::table('hr_festivals')->where('company_id', $c)->orderByDesc('festival_date')->get(['id', 'name', 'year'])->map(fn ($f) => ['value' => $f->id, 'label' => trim("{$f->name} {$f->year}")])->values()->all(),
        ];
    }

    /** @return array<string, string|null> */
    public function defaults(): array
    {
        $today = CarbonImmutable::now(self::TZ);
        $period = DB::table('hr_pay_periods')->where('company_id', $this->companyId)->where('start_date', '<=', $today->toDateString())->where('end_date', '>=', $today->toDateString())->value('id')
            ?? DB::table('hr_pay_periods')->where('company_id', $this->companyId)->orderByDesc('start_date')->value('id');
        $year = (new LeaveBook($this->companyId))->yearOf($today->toDateString());

        return ['date' => $today->toDateString(), 'from' => $today->startOfMonth()->toDateString(), 'to' => $today->toDateString(), 'month' => $today->format('Y-m'), 'period' => $period, 'fiscalYear' => $year?->id];
    }

    /* ── Running ───────────────────────────────────────────────────── */

    /**
     * @param  array<string, string|null>  $f
     * @return array<string, mixed>
     */
    public function run(string $key, array $f): array
    {
        $f = array_merge($this->defaults(), array_filter($f, fn ($v) => $v !== null && $v !== ''));
        if (($f['from'] ?? '') > ($f['to'] ?? '')) {
            [$f['from'], $f['to']] = [$f['to'], $f['from']];
        }
        $out = match ($key) {
            'employee-master', 'employee-list', 'employee-status' => $this->roll($key, $f),
            'employee-assignment' => $this->assignment($f),
            'joining' => $this->joining($f),
            'employee-movement' => $this->movement($f),
            'total-manpower' => $this->totalManpower($f),
            'designation-manpower' => $this->designationManpower($f),
            'floor-manpower', 'department-manpower', 'section-manpower', 'line-manpower' => $this->groupManpower($key, $f),
            'daily-attendance', 'present' => $this->dailyAttendance($key, $f),
            'monthly-attendance' => $this->monthlyAttendance($f),
            'absent' => $this->absent($f),
            'late', 'early-out', 'missing-punch' => $this->punctuality($key, $f),
            'shift-attendance' => $this->shiftAttendance($f),
            'attendance-correction' => $this->corrections($f),
            'daily-ot' => $this->dailyOt($f),
            'employee-ot', 'section-ot', 'line-ot', 'ot-salary-summary', 'grade-ot' => $this->groupOt($key, $f),
            'monthly-ot' => $this->monthlyOt($f),
            'ot-correction-history' => $this->otHistory($f),
            'daily-salary' => $this->dailySalary($f),
            'employee-daily-salary' => $this->employeeDailySalary($f),
            'monthly-salary', 'salary-register', 'salary-deduction', 'bank-salary-sheet', 'gross-a-salary', 'gross-ab-combined' => $this->slips($key, $f),
            'gross-b-salary' => ['tiles' => [], 'columns' => [], 'rows' => [], 'empty' => __('hrpayroll.rp.e.no_complex')],
            'payslip' => $this->payslip($f),
            'allowance' => $this->allowances($f),
            'bonus' => $this->bonus($f),
            'payroll-adjustment' => $this->adjustments($f),
            'leave' => $this->leave($f),
            'leave-balance' => $this->leaveBalance($f),
            'promotion-increment' => $this->promotions($f),
            'transfer' => $this->transfers($f),
            'final-settlement' => $this->settlements($f),
        };
        $rows = $out['rows'];
        $truncated = count($rows) > self::MAX_ROWS;

        return [
            'tiles' => $out['tiles'] ?? [], 'columns' => $out['columns'], 'rows' => $truncated ? array_slice($rows, 0, self::MAX_ROWS) : $rows, 'foot' => $out['foot'] ?? null,
            'empty' => $out['empty'] ?? __('hrpayroll.rp.e.none'), 'note' => $out['note'] ?? null, 'truncated' => $truncated, 'count' => count($rows), 'filters' => $f,
        ];
    }

    /* ── Employee ──────────────────────────────────────────────────── */

    private function roll(string $key, array $f): array
    {
        $q = $this->people($f);
        if (! empty($f['status'])) {
            $q->where('e.status', $f['status']);
        }
        $all = $q->get();
        $finger = $key === 'employee-master' ? DB::table('dev_fingerprints')->where('company_id', $this->companyId)->whereIn('employee_id', $all->pluck('id'))->distinct()->pluck('employee_id')->flip() : collect();
        $rows = $all->map(fn ($e) => [
            'code' => $e->employee_code, 'name' => $e->name, 'card' => $e->card_no, 'nid' => $e->nid, 'dob' => self::d($e->date_of_birth), 'gender' => self::label($e->gender), 'father' => $e->father_name,
            'mother' => $e->mother_name, 'religion' => self::label($e->religion), 'marital' => self::label($e->marital_status), 'blood' => $e->blood_group, 'mobile' => $e->mobile, 'email' => $e->email,
            'joining' => self::d($e->joining_date), 'type' => $e->type_name, 'building' => $e->building, 'floor' => $e->floor, 'department' => $e->department, 'section' => $e->section, 'line' => self::lineLabel($e->line_code, $e->line),
            'designation' => $e->designation, 'grade' => $e->grade, 'shift' => $e->shift, 'structure' => $e->type_name ? trim($e->type_name.($e->gazette ? " — {$e->gazette}" : '')) : null,
            'gross_a' => self::f($e->gross_a), 'gross_b' => self::f($e->gross_b), 'fingerprint' => __(isset($finger[$e->id]) ? 'hrpayroll.rp.registered' : 'hrpayroll.rp.not_registered'),
            'present_address' => $e->present_address, 'permanent_address' => $e->permanent_address,
            'emergency' => trim(implode(' · ', array_filter([$e->emergency_name, $e->emergency_relation, $e->emergency_mobile]))) ?: null,
            'status' => self::s($e->status), 'separation' => self::d($e->separation_date),
        ])->values()->all();
        $cols = match ($key) {
            'employee-master' => ['code', 'name', 'card:card_proxy', 'nid', 'dob:date', 'gender', 'father', 'mother', 'religion', 'marital', 'blood', 'mobile', 'email', 'joining:date', 'type:employee_type',
                'building', 'floor', 'department', 'section', 'line', 'designation', 'grade', 'shift', 'structure', 'gross_a:money', 'gross_b:money', 'fingerprint', 'present_address', 'permanent_address', 'emergency', 'status', 'separation:date'],
            'employee-list' => ['code', 'name', 'card', 'designation', 'department', 'section', 'line', 'joining:date', 'mobile', 'status'],
            default => ['code', 'name', 'designation', 'department', 'section', 'line', 'joining:date', 'status', 'separation:date'],
        };
        $tiles = $key === 'employee-status'
            ? array_map(fn ($s) => self::tile("s.{$s}", $all->where('status', $s)->count()), self::EMP_STATUS)
            : [self::tile('t.employees', count($rows))];

        return ['tiles' => $tiles, 'columns' => self::cols($cols), 'rows' => $rows];
    }

    private function assignment(array $f): array
    {
        $people = $this->people($f)->where('e.status', 'ACTIVE')->get();
        $open = DB::table('hr_employee_assignments')->where('company_id', $this->companyId)->whereIn('employee_id', $people->pluck('id'))->whereNull('effective_to')
            ->orderBy('effective_from')->get(['employee_id', 'effective_from'])->keyBy('employee_id');
        $rows = $people->map(function ($e) use ($open) {
            $a = $open[$e->id] ?? null;

            return ['code' => $e->employee_code, 'name' => $e->name, 'card' => $e->card_no, 'building' => $e->building, 'floor' => $e->floor, 'department' => $e->department, 'section' => $e->section,
                'line' => self::lineLabel($e->line_code, $e->line), 'designation' => $e->designation, 'grade' => $e->grade, 'shift' => $e->shift, 'from' => self::d($a?->effective_from),
                'state' => $a ? 'ASSIGNED' : 'PENDING', 'status' => self::s($a ? 'ASSIGNED' : 'PENDING')];
        });
        $all = $rows;
        if (! empty($f['status'])) {
            $rows = $rows->where('state', $f['status']);
        }

        return [
            'tiles' => [self::tile('t.employees', $all->count()), self::tile('s.ASSIGNED', $all->where('state', 'ASSIGNED')->count()), self::tile('s.PENDING', $all->where('state', 'PENDING')->count())],
            'columns' => self::cols(['code', 'name', 'card', 'building', 'floor', 'department', 'section', 'line', 'designation', 'grade', 'shift', 'from:date:assigned_from', 'status:text:assignment_status']),
            'rows' => $rows->values()->all(),
        ];
    }

    private function joining(array $f): array
    {
        $rows = $this->people($f)->whereBetween('e.joining_date', [$f['from'], $f['to']])->reorder()->orderBy('e.joining_date')->orderBy('e.employee_code')->get()
            ->map(fn ($e) => ['joining' => self::d($e->joining_date), 'code' => $e->employee_code, 'name' => $e->name, 'card' => $e->card_no, 'designation' => $e->designation, 'department' => $e->department,
                'section' => $e->section, 'line' => self::lineLabel($e->line_code, $e->line), 'gross_a' => self::f($e->gross_a), 'status' => self::s($e->status)])->values();

        return [
            'tiles' => [self::tile('t.joined', $rows->count())],
            'columns' => self::cols(['joining:date', 'code', 'name', 'card', 'designation', 'department', 'section', 'line', 'gross_a:money', 'status']),
            'rows' => $rows->all(), 'foot' => $rows->isEmpty() ? null : ['joining' => __('hrpayroll.rp.total'), 'code' => $rows->count(), 'gross_a' => round($rows->sum('gross_a'), 2)],
        ];
    }

    private function movement(array $f): array
    {
        $ids = $this->people($f)->pluck('e.id');
        $people = $this->people([])->whereIn('e.id', $ids)->get()->keyBy('id');
        $names = fn (string $table) => DB::table($table)->where('company_id', $this->companyId)->pluck('name', 'id');
        [$dept, $sect, $desig, $grade] = [$names('departments'), $names('sections'), $names('designations'), DB::table('grades')->where('company_id', $this->companyId)->pluck('name', 'id')];
        $lines = DB::table('production_lines')->where('company_id', $this->companyId)->get(['id', 'code', 'name'])->keyBy('id');
        $line = fn ($id) => $id && isset($lines[$id]) ? self::lineLabel($lines[$id]->code, $lines[$id]->name) : null;
        $place = fn ($d, $s, $l) => implode(' / ', array_filter([$dept[$d] ?? null, $sect[$s] ?? null, $line($l)])) ?: '—';
        $range = fn (Builder $q, string $col) => $q->where('company_id', $this->companyId)->whereIn('employee_id', $ids)->whereBetween($col, [$f['from'], $f['to']]);
        $out = collect();
        foreach ($range(DB::table('hr_transfers'), 'effective_date')->get() as $r) {
            $out->push(['kind' => 'TRANSFER', 'date' => $r->effective_date, 'ref' => $r->ref_no, 'e' => $r->employee_id, 'from' => $place($r->old_department_id, $r->old_section_id, $r->old_line_id),
                'to' => $place($r->new_department_id, $r->new_section_id, $r->new_line_id), 'status' => $r->status, 'remarks' => $r->reason]);
        }
        foreach ($range(DB::table('hr_promotions'), 'effective_date')->get() as $r) {
            $out->push(['kind' => 'PROMOTION', 'date' => $r->effective_date, 'ref' => $r->ref_no, 'e' => $r->employee_id,
                'from' => trim(($desig[$r->old_designation_id] ?? '—').($r->old_grade_id ? ' · '.($grade[$r->old_grade_id] ?? '') : '')),
                'to' => trim(($desig[$r->new_designation_id] ?? '—').($r->new_grade_id ? ' · '.($grade[$r->new_grade_id] ?? '') : '')), 'status' => $r->status, 'remarks' => $r->reason]);
        }
        foreach ($range(DB::table('hr_increments'), 'effective_date')->get() as $r) {
            $out->push(['kind' => 'INCREMENT', 'date' => $r->effective_date, 'ref' => $r->ref_no, 'e' => $r->employee_id, 'from' => self::money($r->old_gross), 'to' => self::money($r->new_gross), 'status' => $r->status, 'remarks' => $r->reason]);
        }
        foreach ($range(DB::table('hr_rejoins'), 'rejoin_date')->get() as $r) {
            $out->push(['kind' => 'REJOIN', 'date' => $r->rejoin_date, 'ref' => $r->ref_no, 'e' => $r->employee_id, 'from' => self::s($r->previous_status ?? 'SEPARATED'), 'to' => self::s('ACTIVE'), 'status' => 'APPLIED', 'remarks' => $r->remarks]);
        }
        foreach ($range(DB::table('hr_separations'), 'last_working_date')->get() as $r) {
            $out->push(['kind' => 'SEPARATION', 'sub' => $r->type, 'date' => $r->last_working_date, 'ref' => $r->ref_no, 'e' => $r->employee_id, 'from' => self::s('ACTIVE'),
                'to' => self::s($r->status === 'APPROVED' ? 'SEPARATED' : 'ACTIVE'), 'status' => $r->status, 'remarks' => $r->reason]);
        }
        $all = $out;
        if (! empty($f['status'])) {
            $out = $out->where('kind', $f['status']);
        }
        $rows = $out->sortBy(fn ($r) => substr((string) $r['date'], 0, 10).$r['ref'])->map(function ($r) use ($people) {
            $e = $people[$r['e']] ?? null;
            $kind = __("hrpayroll.rp.s.{$r['kind']}");

            return ['date' => self::d($r['date']), 'movement' => isset($r['sub']) ? "{$kind} (".self::s($r['sub']).')' : $kind, 'ref' => $r['ref'], 'code' => $e?->employee_code, 'name' => $e?->name,
                'department' => $e?->department, 'from' => $r['from'], 'to' => $r['to'], 'status' => self::s($r['status']), 'remarks' => $r['remarks']];
        })->values()->all();

        return [
            'tiles' => array_map(fn ($k) => self::tile("t.m_{$k}", $all->where('kind', $k)->count()), ['TRANSFER', 'PROMOTION', 'INCREMENT', 'REJOIN', 'SEPARATION']),
            'columns' => self::cols(['date:date:effective_date', 'movement', 'ref', 'code', 'name', 'department', 'from:text:from_value', 'to:text:to_value', 'status', 'remarks']),
            'rows' => $rows, 'empty' => __('hrpayroll.rp.e.widen'),
        ];
    }

    /* ── Manpower ──────────────────────────────────────────────────── */

    /** Active employees in scope with their day: present / absent / leave / off, late, missing punch. */
    public function manpower(array $f): Collection
    {
        $people = $this->people($f)->where('e.status', 'ACTIVE')->get();
        $days = DB::table('hr_attendance_days')->where('company_id', $this->companyId)->where('date', $f['date'])->whereIn('employee_id', $people->pluck('id'))->get()->keyBy('employee_id');

        return $people->map(function ($e) use ($days) {
            $d = $days[$e->id] ?? null;
            $e->day = $d;
            $e->state = self::state($d);
            $e->late = (bool) ($d->is_late ?? false);
            $e->missing = ($d->status ?? null) === 'MISSING_PUNCH';
            $e->category = $this->category($e);

            return $e;
        });
    }

    private function totalManpower(array $f): array
    {
        $p = $this->manpower($f);
        $n = max(1, $p->count());
        $assigned = DB::table('hr_employee_assignments')->where('company_id', $this->companyId)->whereIn('employee_id', $p->pluck('id'))->whereNull('effective_to')->distinct()->count('employee_id');
        $measures = [['total_active', $p->count()], ['assigned', $assigned], ['pending_assignment', $p->count() - $assigned]];
        foreach (self::MANPOWER as $cat) {
            $measures[] = ["cat_{$cat}", $p->where('category', $cat)->count()];
        }
        $measures = array_merge($measures, [['present', $p->where('state', 'PRESENT')->count()], ['absent', $p->where('state', 'ABSENT')->count()], ['on_leave', $p->where('state', 'LEAVE')->count()],
            ['off_day', $p->where('state', 'OFF')->count()], ['late', $p->where('late', true)->count()], ['missing_punch', $p->where('missing', true)->count()]]);

        return [
            'tiles' => $this->dayTiles($p),
            'columns' => self::cols(['measure', 'count:int', 'pct:pct:pct_total']),
            'rows' => array_map(fn ($m) => ['measure' => __("hrpayroll.rp.m.{$m[0]}"), 'count' => $m[1], 'pct' => round($m[1] * 100 / $n, 1)], $measures),
        ];
    }

    private function designationManpower(array $f): array
    {
        $p = $this->manpower($f);
        $rows = array_map(function ($cat) use ($p) {
            $g = $p->where('category', $cat);

            return ['category' => __("hrpayroll.rp.s.{$cat}"), 'total' => $g->count(), 'present' => $g->where('state', 'PRESENT')->count(), 'absent' => $g->where('state', 'ABSENT')->count(),
                'leave' => $g->where('state', 'LEAVE')->count(), 'off' => $g->where('state', 'OFF')->count(), 'pct' => $g->count() ? round($g->where('state', 'PRESENT')->count() * 100 / $g->count(), 1) : 0.0];
        }, self::MANPOWER);
        $foot = self::sum($rows, ['total', 'present', 'absent', 'leave', 'off']) + ['category' => __('hrpayroll.rp.total'), 'pct' => $p->count() ? round($p->where('state', 'PRESENT')->count() * 100 / $p->count(), 1) : 0.0];

        return ['tiles' => $this->dayTiles($p), 'columns' => self::cols(['category', 'total:int', 'present:int', 'absent:int', 'leave:int', 'off:int:off_day', 'pct:pct:present_pct']), 'rows' => $rows, 'foot' => $foot];
    }

    private function groupManpower(string $key, array $f): array
    {
        $by = ['floor-manpower' => 'floor', 'department-manpower' => 'department', 'section-manpower' => 'section', 'line-manpower' => 'line'][$key];
        $p = $this->manpower($f);
        $ot = [];
        if ($by === 'line') {
            foreach ($this->otDays($f['date'], $f['date'], $p) as $d) {
                $ot[$d['e']->id] = $d['hours'];
            }
        }
        $label = fn ($e) => $by === 'line' ? self::lineLabel($e->line_code, $e->line) : $e->{$by};
        $groups = $p->groupBy(fn ($e) => $label($e) ?? '')->sortKeys();
        $rows = [];
        foreach ($groups as $name => $g) {
            $row = [$by => $name === '' ? __($by === 'line' || $by === 'floor' ? 'hrpayroll.rp.not_assigned' : 'hrpayroll.rp.none') : $name, 'total' => $g->count()];
            foreach (self::MANPOWER as $cat) {
                $row[strtolower($cat)] = $g->where('category', $cat)->count();
            }
            $row += ['present' => $g->where('state', 'PRESENT')->count(), 'absent' => $g->where('state', 'ABSENT')->count(), 'leave' => $g->where('state', 'LEAVE')->count()];
            if ($by === 'line') {
                $row += ['ot_emp' => $g->filter(fn ($e) => ($ot[$e->id] ?? 0) > 0)->count(), 'ot_hours' => round($g->sum(fn ($e) => $ot[$e->id] ?? 0), 2)];
            }
            $rows[$name === '' ? "\u{FFFF}" : $name] = $row;
        }
        ksort($rows);
        $rows = array_values($rows);
        $nums = array_merge(['total'], array_map('strtolower', self::MANPOWER), ['present', 'absent', 'leave'], $by === 'line' ? ['ot_emp', 'ot_hours'] : []);
        $cols = array_merge([$by], array_map(fn ($k) => in_array($k, ['ot_hours'], true) ? "{$k}:hours" : "{$k}:int", $nums));
        $real = $groups->keys()->filter(fn ($k) => $k !== '')->count();

        return [
            'tiles' => array_merge([self::tile("t.{$by}s", $real)], $this->dayTiles($p)),
            'columns' => self::cols($cols), 'rows' => $rows, 'foot' => self::sum($rows, $nums) + [$by => __('hrpayroll.rp.total')],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function dayTiles(Collection $p): array
    {
        return [self::tile('t.total', $p->count()), self::tile('t.present', $p->where('state', 'PRESENT')->count()), self::tile('t.absent', $p->where('state', 'ABSENT')->count()), self::tile('t.on_leave', $p->where('state', 'LEAVE')->count())];
    }

    /* ── Attendance ────────────────────────────────────────────────── */

    private function dailyAttendance(string $key, array $f): array
    {
        $shifts = DB::table('hr_shifts')->where('company_id', $this->companyId)->get()->keyBy('id');
        $people = $this->people($f)->get()->keyBy('id');
        $days = DB::table('hr_attendance_days')->where('company_id', $this->companyId)->where('date', $f['date'])->whereIn('employee_id', $people->keys())
            ->when(! empty($f['shift']), fn ($q) => $q->where('shift_id', $f['shift']))->get();
        $all = $days->map(fn ($d) => $this->dayRow($d, $people[$d->employee_id], $shifts))->sortBy('code')->values();
        if ($key === 'present') {
            $rows = $all->filter(fn ($r) => in_array($r['_status'], ['PRESENT', 'MISSING_PUNCH'], true))->values();

            return [
                'tiles' => [self::tile('t.present', $rows->count()), self::tile('t.late', $rows->where('_late', true)->count())],
                'columns' => self::cols(['code', 'name', 'designation', 'department', 'section', 'line', 'shift', 'in:text:in_time', 'out:text:out_time', 'worked:hours:working_hrs', 'status']),
                'rows' => $rows->all(),
            ];
        }
        $rows = $all;
        if (! empty($f['status'])) {
            $s = $f['status'];
            $rows = $rows->filter(fn ($r) => match ($s) {
                'LATE' => $r['_late'], 'EARLY_OUT' => $r['_early'], default => $r['_status'] === $s
            })->values();
        }

        return [
            'tiles' => [self::tile('t.records', $all->count()), self::tile('t.present', $all->filter(fn ($r) => in_array($r['_status'], ['PRESENT', 'MISSING_PUNCH'], true))->count()),
                self::tile('t.absent', $all->where('_status', 'ABSENT')->count()), self::tile('t.leave', $all->where('_status', 'LEAVE')->count()), self::tile('t.late', $all->where('_late', true)->count()),
                self::tile('t.missing_punch', $all->where('_status', 'MISSING_PUNCH')->count())],
            'columns' => self::cols(['code', 'name', 'designation', 'department', 'section', 'line', 'shift', 'in:text:in_time', 'out:text:out_time', 'worked:hours:working_hrs', 'late_min:int', 'early_min:int:early_out_min', 'ot:hours:ot_hrs', 'status']),
            'rows' => $rows->all(),
        ];
    }

    private function dayRow(object $d, object $e, Collection $shifts): array
    {
        $shift = $shifts[$d->shift_id] ?? null;
        $status = self::s($d->status);
        if ($d->is_late || $d->is_early_out) {
            $status = implode(' / ', array_filter([$d->is_late ? self::s('LATE') : null, $d->is_early_out ? self::s('EARLY_OUT') : null]));
        }

        return [
            'date' => self::d($d->date), 'code' => $e->employee_code, 'name' => $e->name, 'designation' => $e->designation, 'department' => $e->department, 'section' => $e->section,
            'line' => self::lineLabel($e->line_code, $e->line), 'shift' => self::shiftLabel($shift), 'shift_start' => $shift ? Clock::hm(substr((string) $shift->start_time, 0, 5)) : null,
            'shift_end' => Clock::hm(AttendanceBook::regularEnd($shift)), 'in' => self::hm($d->in_at), 'out' => self::hm($d->out_at),
            'worked' => round(((int) $d->worked_minutes) / 60, 2), 'late_min' => (int) $d->late_minutes, 'early_min' => (int) $d->early_out_minutes, 'ot' => round(((int) $d->ot_minutes) / 60, 2),
            'status' => $status, '_status' => $d->status, '_late' => (bool) $d->is_late, '_early' => (bool) $d->is_early_out, '_e' => $e->id,
        ];
    }

    private function monthlyAttendance(array $f): array
    {
        [$from, $to] = self::month($f['month']);
        $people = $this->people($f)->get()->keyBy('id');
        $days = DB::table('hr_attendance_days')->where('company_id', $this->companyId)->whereBetween('date', [$from, $to])->whereIn('employee_id', $people->keys())->get()->groupBy('employee_id');
        $rows = [];
        foreach ($people as $id => $e) {
            $d = $days[$id] ?? collect();
            if ($d->isEmpty()) {
                continue;
            }
            $rows[] = ['code' => $e->employee_code, 'name' => $e->name, 'designation' => $e->designation, 'department' => $e->department, 'section' => $e->section, 'line' => self::lineLabel($e->line_code, $e->line),
                'present' => $d->whereIn('status', ['PRESENT', 'MISSING_PUNCH'])->count(), 'absent' => $d->where('status', 'ABSENT')->count(), 'leave' => $d->where('status', 'LEAVE')->count(),
                'off' => $d->whereIn('status', ['WEEKLY_OFF', 'HOLIDAY'])->count(), 'late_days' => $d->where('is_late', true)->count(), 'late_min' => (int) $d->sum('late_minutes'),
                'missing' => $d->where('status', 'MISSING_PUNCH')->count(), 'worked' => round($d->sum('worked_minutes') / 60, 2), 'ot' => round($d->sum('ot_minutes') / 60, 2)];
        }
        $nums = ['present', 'absent', 'leave', 'off', 'late_days', 'late_min', 'missing', 'worked', 'ot'];

        return [
            'tiles' => [self::tile('t.employees', count($rows))],
            'columns' => self::cols(['code', 'name', 'designation', 'department', 'section', 'line', 'present:int', 'absent:int', 'leave:int', 'off:int:off_holiday', 'late_days:int', 'late_min:int', 'missing:int:missing_punch', 'worked:hours:working_hrs', 'ot:hours:ot_hrs']),
            'rows' => $rows, 'foot' => $rows ? self::sum($rows, $nums) + ['code' => __('hrpayroll.rp.total'), 'name' => __('hrpayroll.rp.n_employees', ['n' => count($rows)])] : null,
        ];
    }

    private function absent(array $f): array
    {
        $shifts = DB::table('hr_shifts')->where('company_id', $this->companyId)->get()->keyBy('id');
        $people = $this->people($f)->where('e.status', 'ACTIVE')->get();
        $days = DB::table('hr_attendance_days')->where('company_id', $this->companyId)->where('date', $f['date'])->whereIn('employee_id', $people->pluck('id'))->get()->keyBy('employee_id');
        $rows = [];
        foreach ($people as $e) {
            $d = $days[$e->id] ?? null;
            if ($d && $d->status !== 'ABSENT') {
                continue;
            }
            if (! empty($f['shift']) && ($d->shift_id ?? null) !== $f['shift']) {
                continue;
            }
            $rows[] = ['code' => $e->employee_code, 'name' => $e->name, 'designation' => $e->designation, 'department' => $e->department, 'section' => $e->section, 'line' => self::lineLabel($e->line_code, $e->line),
                'shift' => self::shiftLabel($shifts[$d->shift_id ?? ''] ?? null), 'basis' => __($d ? 'hrpayroll.rp.marked_absent' : 'hrpayroll.rp.no_punch'), '_marked' => (bool) $d];
        }
        $marked = count(array_filter($rows, fn ($r) => $r['_marked']));

        return [
            'tiles' => [self::tile('t.absent', count($rows)), self::tile('t.marked_absent', $marked), self::tile('t.no_punch', count($rows) - $marked)],
            'columns' => self::cols(['code', 'name', 'designation', 'department', 'section', 'line', 'shift', 'basis']), 'rows' => $rows,
        ];
    }

    private function punctuality(string $key, array $f): array
    {
        $shifts = DB::table('hr_shifts')->where('company_id', $this->companyId)->get()->keyBy('id');
        $people = $this->people($f)->get()->keyBy('id');
        $q = DB::table('hr_attendance_days')->where('company_id', $this->companyId)->whereBetween('date', [$f['from'], $f['to']])->whereIn('employee_id', $people->keys())
            ->when(! empty($f['shift']), fn ($w) => $w->where('shift_id', $f['shift']));
        match ($key) {
            'late' => $q->where('is_late', true),
            'early-out' => $q->where('is_early_out', true),
            default => $q->where(fn ($w) => $w->where('status', 'MISSING_PUNCH')->orWhere(fn ($x) => $x->whereNotNull('in_at')->whereNull('out_at'))->orWhere(fn ($x) => $x->whereNull('in_at')->whereNotNull('out_at'))),
        };
        $rows = $q->orderBy('date')->get()->map(function ($d) use ($people, $shifts) {
            $r = $this->dayRow($d, $people[$d->employee_id], $shifts);
            $r['missing'] = $d->in_at ? 'OUT' : ($d->out_at ? 'IN' : __('hrpayroll.rp.in_out'));

            return $r;
        })->sortBy(fn ($r) => $r['date'].$r['code'])->values();
        $base = ['date:date', 'code', 'name', 'designation', 'department', 'section', 'line', 'shift'];
        [$cols, $tiles, $foot] = match ($key) {
            'late' => [array_merge($base, ['shift_start', 'in:text:in_time', 'late_min:int']),
                [self::tile('t.late_entries', $rows->count()), self::tile('t.employees', $rows->unique('_e')->count()), self::tile('t.late_minutes', $rows->sum('late_min'))], ['late_min' => $rows->sum('late_min')]],
            'early-out' => [array_merge($base, ['shift_end', 'out:text:out_time', 'early_min:int:early_out_min']),
                [self::tile('t.early_outs', $rows->count()), self::tile('t.employees', $rows->unique('_e')->count())], ['early_min' => $rows->sum('early_min')]],
            default => [array_merge($base, ['in:text:in_time', 'out:text:out_time', 'missing']),
                [self::tile('t.missing_punches', $rows->count()), self::tile('t.employees', $rows->unique('_e')->count())], null],
        };

        return ['tiles' => $tiles, 'columns' => self::cols($cols), 'rows' => $rows->all(), 'foot' => $foot && $rows->isNotEmpty() ? $foot + ['date' => __('hrpayroll.rp.total')] : null];
    }

    private function shiftAttendance(array $f): array
    {
        $shifts = DB::table('hr_shifts')->where('company_id', $this->companyId)->where('active', true)->orderBy('start_time')->get();
        $p = $this->manpower($f);
        $rows = [];
        $groups = $p->groupBy(fn ($e) => $e->day->shift_id ?? '');
        foreach ($shifts as $s) {
            if (! empty($f['shift']) && $f['shift'] !== $s->id) {
                continue;
            }
            $rows[] = $this->shiftRow($s->name, Clock::range(substr((string) $s->start_time, 0, 5), substr((string) $s->end_time, 0, 5)), $groups[$s->id] ?? collect());
        }
        if (empty($f['shift'])) {
            $rows[] = $this->shiftRow(__('hrpayroll.rp.no_shift'), '—', $groups[''] ?? collect());
        }
        $nums = ['employees', 'present', 'absent', 'leave', 'late', 'missing'];

        return [
            'tiles' => [self::tile('t.employees', $p->count()), self::tile('t.present', $p->where('state', 'PRESENT')->count()), self::tile('t.absent', $p->where('state', 'ABSENT')->count())],
            'columns' => self::cols(['shift', 'time', 'employees:int', 'present:int', 'absent:int', 'leave:int', 'late:int', 'missing:int:missing_punch']),
            'rows' => $rows, 'foot' => self::sum($rows, $nums) + ['shift' => __('hrpayroll.rp.total')],
        ];
    }

    private function shiftRow(string $name, string $time, Collection $g): array
    {
        return ['shift' => $name, 'time' => $time, 'employees' => $g->count(), 'present' => $g->where('state', 'PRESENT')->count(), 'absent' => $g->where('state', 'ABSENT')->count(),
            'leave' => $g->where('state', 'LEAVE')->count(), 'late' => $g->where('late', true)->count(), 'missing' => $g->where('missing', true)->count()];
    }

    private function corrections(array $f): array
    {
        $people = $this->people($f)->get()->keyBy('id');
        $all = DB::table('hr_attendance_corrections as k')->leftJoin('users as r', 'r.id', '=', 'k.requested_by')->leftJoin('users as a', 'a.id', '=', 'k.approved_by')
            ->where('k.company_id', $this->companyId)->whereIn('k.employee_id', $people->keys())
            ->whereRaw("(k.created_at at time zone 'UTC' at time zone ?)::date between ? and ?", [self::TZ, $f['from'], $f['to']])
            ->orderBy('k.created_at')->get(['k.*', 'r.name as requester', 'a.name as approver']);
        $rows = $all->when(! empty($f['status']), fn ($c) => $c->where('status', $f['status']))->map(function ($k) use ($people) {
            $e = $people[$k->employee_id];
            $in = $k->correct_in !== null && substr((string) $k->correct_in, 0, 5) !== substr((string) $k->old_in, 0, 5);
            $out = $k->correct_out !== null && substr((string) $k->correct_out, 0, 5) !== substr((string) $k->old_out, 0, 5);
            $t = fn ($v) => $v ? substr((string) $v, 0, 5) : '—';
            [$old, $new] = match (true) {
                $in && ! $out => [$t($k->old_in), $t($k->correct_in)],
                $out && ! $in => [$t($k->old_out), $t($k->correct_out)],
                default => [$t($k->old_in).'-'.$t($k->old_out), $t($k->correct_in).'-'.$t($k->correct_out)],
            };

            return [
                'corrected_on' => self::localDate($k->created_at), 'date' => self::d($k->date), 'code' => $e->employee_code, 'name' => $e->name, 'department' => $e->department,
                'correction' => __(($k->old_in === null || $k->old_out === null) ? 'hrpayroll.rp.miss_punch_confirmation' : 'hrpayroll.rp.daily_correction'),
                'punch' => __($in && $out ? 'hrpayroll.rp.in_out' : ($in ? 'hrpayroll.rp.in' : 'hrpayroll.rp.out')), 'old' => $old, 'new' => $new, 'reason' => $k->reason,
                'requested_by' => $k->requester, 'approved_by' => $k->status === 'PENDING' ? null : $k->approver, 'approved_on' => $k->status === 'PENDING' ? null : self::localDate($k->decided_at), 'status' => self::s($k->status),
            ];
        })->values()->all();

        return [
            'tiles' => [self::tile('t.corrections', $all->count()), self::tile('s.PENDING', $all->where('status', 'PENDING')->count()), self::tile('s.APPROVED', $all->where('status', 'APPROVED')->count()), self::tile('s.REJECTED', $all->where('status', 'REJECTED')->count())],
            'columns' => self::cols(['corrected_on:date:correction_date', 'date:date:attendance_date', 'code', 'name', 'department', 'correction', 'punch', 'old', 'new:text:corrected', 'reason', 'requested_by', 'approved_by', 'approved_on:date', 'status']),
            'rows' => $rows,
        ];
    }

    /* ── OT ────────────────────────────────────────────────────────── */

    /**
     * Each OT day of the people in a range: attendance OT, the hours and rate paid (a correction's where saved) and its type.
     *
     * @return list<array<string, mixed>>
     */
    public function otDays(string $from, string $to, Collection $people, ?string $type = null): array
    {
        if ($people->isEmpty()) {
            return [];
        }
        $byId = $people->keyBy('id');
        $days = DB::table('hr_attendance_days as a')->leftJoin('hr_ot_corrections as k', fn ($j) => $j->on('k.employee_id', '=', 'a.employee_id')->on('k.date', '=', 'a.date'))
            ->leftJoin('hr_shifts as s', 's.id', '=', 'a.shift_id')
            ->where('a.company_id', $this->companyId)->whereIn('a.employee_id', $byId->keys())->whereBetween('a.date', [$from, $to])
            ->where(fn ($q) => $q->where('a.ot_minutes', '>', 0)->orWhereNotNull('k.id'))->orderBy('a.date')
            ->get(['a.employee_id', 'a.date', 'a.ot_minutes', 'a.ot_type', 'a.in_at', 'a.out_at', 's.start_time', 's.end_time', 's.working_hours', 's.break_min', 'k.id as k_id', 'k.hours as k_hours', 'k.rate as k_rate']);
        $ids = $days->pluck('employee_id')->unique()->values();
        $subjects = $ids->map(fn ($id) => $byId[$id])->values();
        $rates = $this->salary()->rates($ids->all(), $subjects);
        $standing = (new OtBook($this->companyId))->eligibility($subjects);
        $out = [];
        foreach ($days as $d) {
            $original = round(((int) $d->ot_minutes) / 60, 2);
            $hours = $d->k_id ? (float) $d->k_hours : $original;
            $rate = $d->k_id ? (float) $d->k_rate : ($rates[$d->employee_id] ?? 0.0);
            $t = $d->ot_type ?: ($standing[$d->employee_id]['type'] ?? 'GENERAL');
            $t = $t === 'WORKER' ? 'WORKER' : 'GENERAL';
            if ($type && $type !== $t) {
                continue;
            }
            $out[] = ['e' => $byId[$d->employee_id], 'date' => substr((string) $d->date, 0, 10), 'original' => $original, 'hours' => $hours, 'rate' => $rate, 'amount' => round($hours * $rate, 2),
                'original_amount' => round($original * ($rates[$d->employee_id] ?? 0.0), 2), 'corrected' => (bool) $d->k_id, 'type' => $t, 'in' => self::hm($d->in_at), 'out' => self::hm($d->out_at),
                'shift_end' => Clock::hm(AttendanceBook::regularEnd($d))];
        }

        return $out;
    }

    private function otPeople(array $f): Collection
    {
        return $this->people($f)->when(! empty($f['grade']), fn ($q) => $q->where('e.grade_id', $f['grade']))->get();
    }

    private function dailyOt(array $f): array
    {
        $days = collect($this->otDays($f['date'], $f['date'], $this->otPeople($f), $f['otType'] ?? null));
        $rows = $days->map(fn ($d) => ['code' => $d['e']->employee_code, 'name' => $d['e']->name, 'department' => $d['e']->department, 'designation' => $d['e']->designation, 'grade' => $d['e']->grade,
            'ot_type' => __("hrpayroll.rp.s.{$d['type']}"), 'shift_end' => $d['shift_end'], 'in' => $d['in'], 'out' => $d['out'], 'original' => $d['original'], 'hours' => $d['hours'], 'rate' => $d['rate'],
            'amount' => $d['amount'], 'correction' => __($d['corrected'] ? 'hrpayroll.rp.corrected' : 'hrpayroll.rp.auto')])->sortBy('code')->values();
        $w = $days->where('type', 'WORKER');

        return [
            'tiles' => [self::tile('t.ot_employees', $days->unique(fn ($d) => $d['e']->id)->count()), self::tile('t.ot_hours', $days->sum('hours'), 'hours'), self::tile('t.ot_salary', $days->sum('amount'), 'money'),
                self::tile('t.original_ot_hours', $days->sum('original'), 'hours'), self::tile('t.original_ot_salary', $days->sum('original_amount'), 'money'), self::tile('t.corrected_days', $days->where('corrected', true)->count()),
                self::tile('t.worker_ot_employees', $w->unique(fn ($d) => $d['e']->id)->count()), self::tile('t.worker_ot_hours', $w->sum('hours'), 'hours'), self::tile('t.worker_ot_salary', $w->sum('amount'), 'money')],
            'columns' => self::cols(['code', 'name', 'department', 'designation', 'grade', 'ot_type', 'shift_end', 'in:text:in_time', 'out:text:out_time', 'original:hours:original_hrs', 'hours:hours:ot_hrs', 'rate:money:rate', 'amount:money:ot_salary', 'correction']),
            'rows' => $rows->all(), 'foot' => $rows->isEmpty() ? null : ['code' => __('hrpayroll.rp.total')] + self::sum($rows->all(), ['original', 'hours', 'amount']),
            'note' => __('hrpayroll.rp.n.ot'), 'empty' => __('hrpayroll.rp.n.ot'),
        ];
    }

    private function groupOt(string $key, array $f): array
    {
        $days = collect($this->otDays($f['from'], $f['to'], $this->otPeople($f), $f['otType'] ?? null));
        $by = ['employee-ot' => 'employee', 'section-ot' => 'section', 'line-ot' => 'line', 'ot-salary-summary' => 'department', 'grade-ot' => 'grade'][$key];
        $label = fn ($e) => match ($by) {
            'employee' => $e->employee_code.' · '.$e->name, 'line' => self::lineLabel($e->line_code, $e->line) ?? __('hrpayroll.rp.no_line'), default => $e->{$by} ?? __('hrpayroll.rp.none'),
        };
        $rows = $days->groupBy(fn ($d) => $label($d['e']))->sortKeys()->map(function ($g, $name) use ($by) {
            $e = $g->first()['e'];
            $row = [$by => $name];
            if ($by === 'employee') {
                $row += ['designation' => $e->designation, 'department' => $e->department, 'line' => self::lineLabel($e->line_code, $e->line), 'ot_days' => $g->count()];
            } else {
                $row['ot_employees'] = $g->unique(fn ($d) => $d['e']->id)->count();
                if ($by === 'grade') {
                    $row['ot_days'] = $g->count();
                }
            }
            $row += ['original' => round($g->sum('original'), 2), 'hours' => round($g->sum('hours'), 2)];
            if ($by === 'grade') {
                $row['avg_rate'] = $g->sum('hours') > 0 ? round($g->sum('amount') / $g->sum('hours'), 2) : 0.0;
            }
            $row['amount'] = round($g->sum('amount'), 2);

            return $row;
        })->values()->all();
        $cols = match ($by) {
            'employee' => ['employee', 'designation', 'department', 'line', 'ot_days:int', 'original:hours:original_hrs', 'hours:hours:ot_hrs', 'amount:money:ot_amount'],
            'grade' => ['grade', 'ot_employees:int', 'ot_days:int', 'original:hours:original_ot_hrs', 'hours:hours:ot_hrs', 'avg_rate:money', 'amount:money:ot_salary'],
            default => [$by, 'ot_employees:int', 'original:hours:original_hrs', 'hours:hours:ot_hrs', 'amount:money:ot_amount'],
        };
        $people = $days->unique(fn ($d) => $d['e']->id)->count();
        $tiles = [self::tile('t.ot_employees', $people), self::tile('t.ot_hours', $days->sum('hours'), 'hours'), self::tile($by === 'grade' ? 't.ot_salary' : 't.ot_amount', $days->sum('amount'), 'money')];
        if ($by === 'grade') {
            $w = $days->where('type', 'WORKER');
            $tiles = array_merge([self::tile('t.grades', count($rows))], $tiles, [self::tile('t.worker_ot_hours', $w->sum('hours'), 'hours'), self::tile('t.worker_ot_salary', $w->sum('amount'), 'money')]);
        }
        $foot = $rows ? [$by => __('hrpayroll.rp.total')] + self::sum($rows, array_values(array_intersect(['ot_days', 'original', 'hours', 'amount'], array_keys($rows[0])))) : null;
        if ($foot && $by !== 'employee') {
            $foot['ot_employees'] = $people;
        }

        return ['tiles' => $tiles, 'columns' => self::cols($cols), 'rows' => $rows, 'foot' => $foot, 'note' => __('hrpayroll.rp.n.ot')];
    }

    private function monthlyOt(array $f): array
    {
        [$from, $to] = self::month($f['month']);
        $days = collect($this->otDays($from, $to, $this->otPeople($f), $f['otType'] ?? null));
        $rows = $days->groupBy(fn ($d) => $d['e']->employee_code)->sortKeys()->map(function ($g) {
            $e = $g->first()['e'];
            $gen = $g->where('type', 'GENERAL');
            $wk = $g->where('type', 'WORKER');

            return ['code' => $e->employee_code, 'name' => $e->name, 'department' => $e->department, 'designation' => $e->designation, 'grade' => $e->grade, 'ot_days' => $g->count(),
                'original' => round($g->sum('original'), 2), 'general_hours' => round($gen->sum('hours'), 2), 'general_amount' => round($gen->sum('amount'), 2), 'worker_hours' => round($wk->sum('hours'), 2),
                'worker_amount' => round($wk->sum('amount'), 2), 'hours' => round($g->sum('hours'), 2), 'amount' => round($g->sum('amount'), 2), 'corrections' => $g->where('corrected', true)->count()];
        })->values()->all();
        $nums = ['ot_days', 'original', 'general_hours', 'general_amount', 'worker_hours', 'worker_amount', 'hours', 'amount', 'corrections'];
        $tiles = [self::tile('t.ot_employees', count($rows)), self::tile('t.ot_hours', $days->sum('hours'), 'hours'), self::tile('t.general_ot_salary', $days->where('type', 'GENERAL')->sum('amount'), 'money'),
            self::tile('t.worker_ot_hours_m', $days->where('type', 'WORKER')->sum('hours'), 'hours'), self::tile('t.worker_ot_salary_m', $days->where('type', 'WORKER')->sum('amount'), 'money'),
            self::tile('t.total_payable', $days->sum('amount'), 'money'), self::tile('t.original_ot_hours', $days->sum('original'), 'hours'), self::tile('t.corrected_days', $days->where('corrected', true)->count())];
        foreach ($days->groupBy(fn ($d) => $d['e']->grade ?? '')->sortKeys() as $grade => $g) {
            if ($grade !== '') {
                $tiles[] = ['label' => $grade, 'value' => round($g->sum('hours'), 2).' h · ৳'.number_format($g->sum('amount'), 2), 'kind' => 'text'];
            }
        }

        return [
            'tiles' => $tiles,
            'columns' => self::cols(['code:text:employee_id', 'name', 'department', 'designation', 'grade', 'ot_days:int', 'original:hours:original_ot_hrs', 'general_hours:hours:general_ot_hrs', 'general_amount:money:general_ot_salary',
                'worker_hours:hours:worker_ot_hrs', 'worker_amount:money:worker_ot_salary', 'hours:hours:total_ot_hrs', 'amount:money:total_payable', 'corrections:int']),
            'rows' => $rows, 'foot' => $rows ? self::sum($rows, $nums) + ['code' => __('hrpayroll.rp.total'), 'name' => __('hrpayroll.rp.n_employees', ['n' => count($rows)])] : null, 'note' => __('hrpayroll.rp.n.ot'),
        ];
    }

    private function otHistory(array $f): array
    {
        $people = $this->people($f)->get()->keyBy('id');
        $rows = DB::table('hr_ot_correction_history as h')->leftJoin('users as u', 'u.id', '=', 'h.user_id')->where('h.company_id', $this->companyId)->whereIn('h.employee_id', $people->keys())
            ->whereRaw("(h.created_at at time zone 'UTC' at time zone ?)::date between ? and ?", [self::TZ, $f['from'], $f['to']])->orderBy('h.created_at')->get(['h.*', 'u.name as by']);
        $att = DB::table('hr_attendance_days')->whereIn('employee_id', $rows->pluck('employee_id')->unique())->whereIn('date', $rows->pluck('date')->unique())->get()->keyBy(fn ($d) => $d->employee_id.'|'.substr((string) $d->date, 0, 10));
        $rates = $this->salary()->rates($rows->pluck('employee_id')->unique()->values()->all(), $people->values());
        $before = [];
        $out = [];
        foreach ($rows as $h) {
            $key = $h->employee_id.'|'.substr((string) $h->date, 0, 10);
            $orig = isset($att[$key]) ? round(((int) $att[$key]->ot_minutes) / 60, 2) : 0.0;
            $prev = $before[$key] ?? [$orig, $rates[$h->employee_id] ?? 0.0];
            $reset = $h->action !== 'SAVE';
            $now = $reset ? [$orig, $rates[$h->employee_id] ?? 0.0] : [(float) $h->hours, (float) $h->rate];
            $before[$key] = $now;
            $e = $people[$h->employee_id];
            $out[] = ['at' => self::localDate($h->created_at), 'date' => self::d($h->date), 'code' => $e->employee_code, 'name' => $e->name, 'department' => $e->department,
                'action' => __($reset ? 'hrpayroll.rp.reset' : 'hrpayroll.rp.corrected'), 'hours_before' => $prev[0], 'hours_after' => $now[0], 'rate_before' => $prev[1], 'rate_after' => $now[1],
                'amount_before' => round($prev[0] * $prev[1], 2), 'amount_after' => round($now[0] * $now[1], 2), 'reason' => $h->reason, 'by' => $h->by, '_reset' => $reset, '_k' => $key];
        }
        $net = array_sum(array_map(fn ($r) => $r['amount_after'] - $r['amount_before'], $out));

        return [
            'tiles' => [self::tile('t.changes', count($out)), self::tile('t.employee_days', count(array_unique(array_column($out, '_k')))), self::tile('t.resets', count(array_filter($out, fn ($r) => $r['_reset']))), self::tile('t.net_change', round($net, 2), 'money')],
            'columns' => self::cols(['at:date:changed_on', 'date:date:ot_date', 'code', 'name', 'department', 'action', 'hours_before:hours', 'hours_after:hours', 'rate_before:money', 'rate_after:money', 'amount_before:money', 'amount_after:money', 'reason', 'by:text:changed_by']),
            'rows' => $out, 'empty' => __('hrpayroll.rp.e.widen'),
        ];
    }

    /* ── Salary ────────────────────────────────────────────────────── */

    private function dailySalary(array $f): array
    {
        $people = $this->people($f)->get()->keyBy('id');
        $rows = DB::table('hr_daily_salaries')->where('company_id', $this->companyId)->where('date', $f['date'])->whereIn('employee_id', $people->keys())->get()->map(function ($s) use ($people) {
            $e = $people[$s->employee_id];

            return ['code' => $e->employee_code, 'name' => $e->name, 'designation' => $e->designation, 'department' => $e->department, 'attendance' => self::s($s->attendance_status), 'in' => $s->in_time ? substr((string) $s->in_time, 0, 5) : null,
                'out' => $s->out_time ? substr((string) $s->out_time, 0, 5) : null, 'rate' => self::f($s->gross_a) === null ? null : round((float) $s->gross_a / 30, 2), 'daily' => (float) $s->daily_salary, 'allowance' => (float) $s->allowance,
                'ot_hours' => (float) $s->ot_hours, 'ot_amount' => (float) $s->ot_amount, 'late' => (float) $s->late_deduction, 'other' => (float) $s->other_deduction, 'net' => (float) $s->net, 'status' => self::s($s->status)];
        })->sortBy('code')->values()->all();
        $nums = ['daily', 'allowance', 'ot_hours', 'ot_amount', 'late', 'other', 'net'];

        return [
            'tiles' => [self::tile('t.employees', count($rows)), self::tile('t.net_daily_salary', round(array_sum(array_column($rows, 'net')), 2), 'money')],
            'columns' => self::cols(['code', 'name', 'designation', 'department', 'attendance:text:attendance_status', 'in:text:in_time', 'out:text:out_time', 'rate:money:daily_rate', 'daily:money:daily_salary', 'allowance:money',
                'ot_hours:hours:ot_hrs', 'ot_amount:money', 'late:money:late_ded', 'other:money:other_ded', 'net:money', 'status']),
            'rows' => $rows, 'foot' => $rows ? self::sum($rows, $nums) + ['code' => __('hrpayroll.rp.total')] : null, 'empty' => __('hrpayroll.rp.e.no_daily_salary'),
        ];
    }

    private function employeeDailySalary(array $f): array
    {
        $people = $this->people($f)->get()->keyBy('id');
        $rows = DB::table('hr_daily_salaries')->where('company_id', $this->companyId)->whereBetween('date', [$f['from'], $f['to']])->whereIn('employee_id', $people->keys())->get()->groupBy('employee_id')
            ->map(function ($g, $id) use ($people) {
                $e = $people[$id];

                return ['code' => $e->employee_code, 'name' => $e->name, 'designation' => $e->designation, 'department' => $e->department, 'days' => $g->count(), 'daily' => round($g->sum('daily_salary'), 2),
                    'allowance' => round($g->sum('allowance'), 2), 'ot_hours' => round($g->sum('ot_hours'), 2), 'ot_amount' => round($g->sum('ot_amount'), 2), 'late' => round($g->sum('late_deduction'), 2),
                    'other' => round($g->sum('other_deduction'), 2), 'net' => round($g->sum('net'), 2)];
            })->sortBy('code')->values()->all();
        $nums = ['days', 'daily', 'allowance', 'ot_hours', 'ot_amount', 'late', 'other', 'net'];

        return [
            'tiles' => [self::tile('t.employees', count($rows)), self::tile('t.net', round(array_sum(array_column($rows, 'net')), 2), 'money')],
            'columns' => self::cols(['code', 'name', 'designation', 'department', 'days:int', 'daily:money:daily_salary', 'allowance:money', 'ot_hours:hours:ot_hrs', 'ot_amount:money', 'late:money:late_ded', 'other:money:other_ded', 'net:money']),
            'rows' => $rows, 'foot' => $rows ? self::sum($rows, $nums) + ['code' => __('hrpayroll.rp.total'), 'name' => __('hrpayroll.rp.n_employees', ['n' => count($rows)])] : null,
            'empty' => __('hrpayroll.rp.e.no_daily_salary_range'),
        ];
    }

    /** The pay period's slips for the people in scope, with the run's status. */
    private function periodSlips(array $f): Collection
    {
        $people = $this->people($f)->when(! empty($f['grade']), fn ($q) => $q->where('e.grade_id', $f['grade']))->get()->keyBy('id');
        $pending = DB::table('hr_payroll_adjustments')->where('company_id', $this->companyId)->where('pay_period_id', $f['period'])->where('status', 'PENDING')->pluck('employee_id')->flip();

        return DB::table('hr_salary_slips as s')->join('hr_salary_runs as r', 'r.id', '=', 's.run_id')->leftJoin('grades as g', 'g.id', '=', 's.grade_id')
            ->where('s.company_id', $this->companyId)->where('s.pay_period_id', $f['period'])->whereIn('s.employee_id', $people->keys())
            ->when(! empty($f['structure']), fn ($q) => $q->where('s.structure', $f['structure']))
            ->get(['s.*', 'r.status as run_status', 'g.name as slip_grade'])
            ->map(function ($s) use ($people, $pending) {
                $s->e = $people[$s->employee_id];
                $s->state = isset($pending[$s->employee_id]) ? 'ADJUSTMENT_REQUIRED' : $s->run_status;

                return $s;
            })->sortBy(fn ($s) => $s->e->employee_code)->values();
    }

    private function slips(string $key, array $f): array
    {
        $all = $this->periodSlips($f);
        $slips = $all;
        if (! empty($f['status'])) {
            $slips = $key === 'bank-salary-sheet' ? $slips->where('payment_method', $f['status']) : (in_array($key, ['gross-a-salary', 'gross-ab-combined'], true) ? $slips->where('state', $f['status']) : $slips);
        }
        $ded = fn ($s) => round((float) $s->late_deduction + (float) $s->stamp_charge + (float) $s->other_deduction + (float) $s->loan_advance, 2);
        $base = fn ($s) => ['code' => $s->e->employee_code, 'name' => $s->e->name, 'designation' => $s->e->designation, 'department' => $s->e->department];
        $empty = $all->isEmpty() ? __('hrpayroll.rp.e.no_payroll') : __('hrpayroll.rp.e.none');
        $sumNet = round($slips->sum('net'), 2);
        $foot = fn (array $rows, array $nums) => $rows ? self::sum($rows, $nums) + ['code' => __('hrpayroll.rp.total'), 'name' => __('hrpayroll.rp.n_employees', ['n' => count($rows)])] : null;
        switch ($key) {
            case 'monthly-salary':
                $rows = $slips->map(fn ($s) => $base($s) + ['payable_days' => (float) $s->payable_days, 'absent' => (float) $s->absent_days, 'gross' => (float) $s->earned_gross, 'allowance' => (float) $s->allowances,
                    'ot_hours' => (float) $s->ot_hours, 'ot' => (float) $s->ot_amount, 'bonus' => (float) $s->bonus, 'late' => (float) $s->late_deduction, 'other' => round((float) $s->stamp_charge + (float) $s->other_deduction, 2),
                    'loan' => (float) $s->loan_advance, 'adjustment' => (float) $s->adjustment, 'net' => (float) $s->net, 'status' => self::s($s->state)])->all();
                $nums = ['gross', 'allowance', 'ot_hours', 'ot', 'bonus', 'late', 'other', 'loan', 'adjustment', 'net'];

                return ['tiles' => [self::tile('t.employees', count($rows)), self::tile('t.gross', round($slips->sum('earned_gross'), 2), 'money'), self::tile('t.net_pay', $sumNet, 'money')],
                    'columns' => self::cols(['code', 'name', 'designation', 'department', 'payable_days:num', 'absent:num', 'gross:money', 'allowance:money', 'ot_hours:hours:ot_hrs', 'ot:money', 'bonus:money', 'late:money:late_ded',
                        'other:money:other_ded', 'loan:money', 'adjustment:money', 'net:money:net_pay', 'status']), 'rows' => $rows, 'foot' => $foot($rows, $nums), 'empty' => $empty];
            case 'salary-register':
                $rows = $slips->map(fn ($s) => $base($s) + ['days' => (float) $s->payable_days, 'basic' => (float) $s->basic, 'gross' => (float) $s->earned_gross, 'ot' => (float) $s->ot_amount, 'bonus' => (float) $s->bonus,
                    'late' => (float) $s->late_deduction, 'stamp' => (float) $s->stamp_charge, 'loan' => (float) $s->loan_advance, 'total_ded' => $ded($s), 'net' => (float) $s->net])->all();
                $nums = ['days', 'basic', 'gross', 'ot', 'bonus', 'late', 'stamp', 'loan', 'total_ded', 'net'];

                return ['tiles' => [self::tile('t.employees', count($rows)), self::tile('t.gross', round($slips->sum('earned_gross'), 2), 'money'), self::tile('t.net_pay', $sumNet, 'money')],
                    'columns' => self::cols(['code', 'name', 'designation', 'department', 'days:num', 'basic:money', 'gross:money', 'ot:money', 'bonus:money', 'late:money:late_ded', 'stamp:money:stamp_charge', 'loan:money', 'total_ded:money', 'net:money:net_pay']),
                    'rows' => $rows, 'foot' => $foot($rows, $nums), 'empty' => $empty];
            case 'salary-deduction':
                $rows = $slips->map(fn ($s) => $base($s) + ['gross' => (float) $s->earned_gross, 'late_days' => (int) $s->late_days, 'late' => (float) $s->late_deduction,
                    'headwise' => round((float) $s->stamp_charge + (float) $s->other_deduction, 2), 'loan' => (float) $s->loan_advance, 'total_ded' => $ded($s), 'net' => (float) $s->net])->all();
                $nums = ['gross', 'late_days', 'late', 'headwise', 'loan', 'total_ded', 'net'];

                return ['tiles' => [self::tile('t.late_deduction', round($slips->sum('late_deduction'), 2), 'money'), self::tile('t.headwise', round(array_sum(array_column($rows, 'headwise')), 2), 'money'),
                    self::tile('t.loan_advance', round($slips->sum('loan_advance'), 2), 'money'), self::tile('t.total_deduction', round(array_sum(array_column($rows, 'total_ded')), 2), 'money')],
                    'columns' => self::cols(['code', 'name', 'designation', 'department', 'gross:money', 'late_days:int', 'late:money:late_ded', 'headwise:money:headwise_ded', 'loan:money', 'total_ded:money', 'net:money:net_pay']),
                    'rows' => $rows, 'foot' => $foot($rows, $nums), 'empty' => $empty];
            case 'bank-salary-sheet':
                $rows = $slips->map(fn ($s) => $base($s) + ['bank' => null, 'branch' => null, 'account_title' => null, 'account_no' => null, 'mode' => self::s($s->payment_method), 'net' => (float) $s->net])->all();
                $bank = $all->where('payment_method', 'BANK');
                $cash = $all->where('payment_method', '!=', 'BANK');

                return ['tiles' => [self::tile('t.bank_transfers', $bank->count()), self::tile('t.bank_amount', round($bank->sum('net'), 2), 'money'), self::tile('t.cash_no_account', $cash->count()), self::tile('t.cash_amount', round($cash->sum('net'), 2), 'money')],
                    'columns' => self::cols(['code', 'name', 'designation', 'department', 'bank', 'branch', 'account_title', 'account_no', 'mode', 'net:money:net_pay']),
                    'rows' => $rows, 'foot' => $foot($rows, ['net']), 'empty' => $empty];
            case 'gross-a-salary':
                $rows = $slips->map(fn ($s) => ['code' => $s->e->employee_code, 'name' => $s->e->name, 'department' => $s->e->department, 'designation' => $s->e->designation, 'grade' => $s->slip_grade ?? $s->e->grade,
                    'gross_a' => (float) $s->gross_a, 'payable_days' => self::n($s->payable_days).' / '.self::n($s->period_days), 'earned' => (float) $s->earned_gross, 'allowance' => (float) $s->allowances, 'ot_hours' => (float) $s->ot_hours,
                    'ot' => (float) $s->ot_amount, 'bonus' => (float) $s->bonus, 'deductions' => $ded($s), 'adjustment' => (float) $s->adjustment, 'net' => (float) $s->net, 'status' => self::s($s->state)])->all();
                $nums = ['gross_a', 'earned', 'allowance', 'ot_hours', 'ot', 'bonus', 'deductions', 'adjustment', 'net'];

                return ['tiles' => [self::tile('t.employees', count($rows)), self::tile('t.gross_a', round($slips->sum('gross_a'), 2), 'money'), self::tile('t.net_payable', $sumNet, 'money'), self::tile('s.ADJUSTMENT_REQUIRED', $all->where('state', 'ADJUSTMENT_REQUIRED')->count())],
                    'columns' => self::cols(['code', 'name', 'department', 'designation', 'grade', 'gross_a:money', 'payable_days', 'earned:money:earned_gross', 'allowance:money', 'ot_hours:hours:ot_hrs', 'ot:money:ot_salary', 'bonus:money:festival_bonus',
                        'deductions:money', 'adjustment:money', 'net:money:net_payable', 'status:text:payroll_status']), 'rows' => $rows, 'foot' => $foot($rows, $nums), 'empty' => $empty];
            default: // gross-ab-combined
                $notProcessed = __('hrpayroll.rp.not_in_complex');
                $rows = $slips->map(fn ($s) => ['code' => $s->e->employee_code, 'name' => $s->e->name, 'department' => $s->e->department, 'grade' => $s->slip_grade ?? $s->e->grade, 'gross_a' => (float) $s->gross_a,
                    'gross_b' => self::f($s->gross_b ?? $s->e->gross_b), 'ot' => (float) $s->ot_amount, 'ot_b' => null, 'net_a' => (float) $s->net, 'net_b' => $notProcessed, 'status' => self::s($s->state)])->all();

                return ['tiles' => [self::tile('t.employees', count($rows)), ['label' => __('hrpayroll.rp.t.gross_b_from'), 'value' => $notProcessed, 'kind' => 'text'], self::tile('t.net_payable_a', $sumNet, 'money'), self::tile('t.net_b', 0, 'money')],
                    'columns' => self::cols(['code', 'name', 'department', 'grade', 'gross_a:money', 'gross_b:money', 'ot:money:ot_salary', 'ot_b:money:ot_salary_b', 'net_a:money:net_payable_a', 'net_b:text:net_b', 'status:text:payroll_status']),
                    'rows' => $rows, 'foot' => $foot($rows, ['gross_a', 'gross_b', 'ot', 'net_a']), 'empty' => $empty];
        }
    }

    private function payslip(array $f): array
    {
        $cols = self::cols(['head:text:salary_head', 'kind:text:head_type', 'detail', 'amount:money']);
        if (empty($f['employee'])) {
            return ['columns' => $cols, 'rows' => [], 'empty' => __('hrpayroll.rp.e.employee_required')];
        }
        $s = $this->periodSlips($f)->first();
        if (! $s) {
            return ['columns' => $cols, 'rows' => [], 'empty' => __('hrpayroll.rp.e.no_slip')];
        }
        $lines = json_decode((string) $s->lines, true) ?: [];
        $earn = __('hrpayroll.rp.earning');
        $less = __('hrpayroll.rp.deduction');
        $r = fn (string $head, string $kind, ?string $detail, float $amount) => ['head' => __("hrpayroll.rp.ps.{$head}"), 'kind' => $kind, 'detail' => $detail, 'amount' => round($amount, 2) + 0.0];
        $rows = [$r('gross_a', $earn, null, (float) $s->gross_a), $r('basic', $earn, null, (float) $s->basic),
            $r('earned_gross', $earn, __('hrpayroll.rp.days_of', ['a' => self::n($s->payable_days), 'b' => self::n($s->period_days)]), (float) $s->earned_gross)];
        foreach ($lines['allowances'] ?? [] as $a) {
            $rows[] = ['head' => (string) ($a[0] ?? $a['name'] ?? ''), 'kind' => $earn, 'detail' => null, 'amount' => round((float) ($a[1] ?? $a['amount'] ?? 0), 2)];
        }
        $rows[] = $r('ot', $earn, self::n($s->ot_hours).' h', (float) $s->ot_amount);
        $rows[] = $r('bonus', $earn, null, (float) $s->bonus);
        $rows[] = $r('late', $less, __('hrpayroll.rp.late_days', ['n' => (int) $s->late_days]), -(float) $s->late_deduction);
        $rows[] = $r('stamp', $less, null, -(float) $s->stamp_charge);
        foreach ($lines['deductions'] ?? [] as $d) {
            $rows[] = ['head' => (string) ($d[0] ?? $d['name'] ?? ''), 'kind' => $less, 'detail' => null, 'amount' => 0.0 - round((float) ($d[1] ?? $d['amount'] ?? 0), 2) + 0.0];
        }
        if ((float) $s->loan_advance) {
            $rows[] = $r('loan', $less, null, -(float) $s->loan_advance);
        }
        if ((float) $s->adjustment) {
            $rows[] = $r('adjustment', (float) $s->adjustment > 0 ? $earn : $less, null, (float) $s->adjustment);
        }

        return [
            'tiles' => [['label' => __('hrpayroll.rp.t.employee'), 'value' => $s->e->employee_code.' · '.$s->e->name, 'kind' => 'text'], self::tile('t.payable_days', (float) $s->payable_days, 'num'),
                self::tile('t.net_pay', (float) $s->net, 'money'), ['label' => __('hrpayroll.rp.t.status'), 'value' => self::s($s->state), 'kind' => 'text']],
            'columns' => $cols, 'rows' => $rows, 'foot' => ['head' => __('hrpayroll.rp.ps.net'), 'amount' => (float) $s->net],
        ];
    }

    private function allowances(array $f): array
    {
        $people = $this->people($f)->get();
        $byCode = $people->keyBy('employee_code');
        $rows = DB::table('hr_allowances as x')->join('hr_pay_elements as p', 'p.id', '=', 'x.pay_element_id')->join('users as u', 'u.id', '=', 'x.user_id')
            ->where('x.company_id', $this->companyId)->where('x.pay_period_id', $f['period'])->whereIn('u.employee_code', $byCode->keys())->get(['u.employee_code', 'p.name as allowance', 'x.amount'])
            ->map(function ($a) use ($byCode) {
                $e = $byCode[$a->employee_code];

                return ['code' => $e->employee_code, 'name' => $e->name, 'designation' => $e->designation, 'department' => $e->department, 'allowance' => $a->allowance, 'amount' => (float) $a->amount];
            })->sortBy(fn ($r) => $r['code'].$r['allowance'])->values()->all();

        return [
            'tiles' => [self::tile('t.employees', count(array_unique(array_column($rows, 'code')))), self::tile('t.total_allowance', round(array_sum(array_column($rows, 'amount')), 2), 'money')],
            'columns' => self::cols(['code', 'name', 'designation', 'department', 'allowance', 'amount:money']), 'rows' => $rows,
            'foot' => $rows ? ['code' => __('hrpayroll.rp.total'), 'amount' => round(array_sum(array_column($rows, 'amount')), 2)] : null, 'empty' => __('hrpayroll.rp.e.widen'),
        ];
    }

    private function bonus(array $f): array
    {
        $people = $this->people($f)->get()->keyBy('id');
        $rows = DB::table('hr_bonus_lines as l')->join('hr_bonus_runs as r', 'r.id', '=', 'l.run_id')->join('hr_festivals as fe', 'fe.id', '=', 'r.festival_id')
            ->where('r.company_id', $this->companyId)->whereIn('l.employee_id', $people->keys())->where('l.eligible', true)
            ->when(! empty($f['festival']), fn ($q) => $q->where('r.festival_id', $f['festival']), fn ($q) => $q->where('r.pay_period_id', $f['period']))
            ->get(['l.*', 'fe.name as festival', 'r.status as run_status'])->map(function ($l) use ($people) {
                $e = $people[$l->employee_id];

                return ['festival' => $l->festival, 'code' => $e->employee_code, 'name' => $e->name, 'designation' => $e->designation, 'department' => $e->department, 'service_days' => (int) $l->service_days,
                    'basis' => self::s($l->basis), 'basis_amount' => (float) $l->basis_amount, 'rule' => $l->pct !== null ? self::n($l->pct).'%' : ($l->fixed !== null ? self::money($l->fixed) : $l->rule),
                    'amount' => (float) $l->amount, 'status' => self::s($l->run_status)];
            })->sortBy('code')->values()->all();

        return [
            'tiles' => [self::tile('t.employees', count($rows)), self::tile('t.total_bonus', round(array_sum(array_column($rows, 'amount')), 2), 'money')],
            'columns' => self::cols(['festival', 'code', 'name', 'designation', 'department', 'service_days:int', 'basis', 'basis_amount:money', 'rule:text:bonus_rule', 'amount:money:bonus_amount', 'status']),
            'rows' => $rows, 'foot' => $rows ? ['festival' => __('hrpayroll.rp.total'), 'amount' => round(array_sum(array_column($rows, 'amount')), 2)] : null, 'empty' => __('hrpayroll.rp.e.widen'),
        ];
    }

    private function adjustments(array $f): array
    {
        $people = $this->people($f)->get()->keyBy('id');
        $periods = DB::table('hr_pay_periods')->where('company_id', $this->companyId)->pluck('period_no', 'id');
        $all = DB::table('hr_payroll_adjustments')->where('company_id', $this->companyId)->whereIn('employee_id', $people->keys())
            ->whereRaw("(created_at at time zone 'UTC' at time zone ?)::date between ? and ?", [self::TZ, $f['from'], $f['to']])->orderBy('created_at')->get();
        $rows = $all->when(! empty($f['status']), fn ($c) => $c->where('status', $f['status']))->map(function ($a) use ($people, $periods) {
            $e = $people[$a->employee_id];

            return ['ref' => $a->ref_no, 'raised' => self::localDate($a->created_at), 'code' => $e->employee_code, 'name' => $e->name, 'department' => $e->department, 'period' => $periods[$a->pay_period_id] ?? null,
                'kind' => self::s($a->kind), 'original' => (float) $a->original, 'revised' => (float) $a->revised, 'amount' => (float) $a->amount, 'status' => self::s($a->status), 'paid_in' => $periods[$a->paid_in_period_id] ?? null];
        })->values()->all();

        return [
            'tiles' => [self::tile('t.adjustments', $all->count()), self::tile('s.PENDING', $all->where('status', 'PENDING')->count()), self::tile('t.approved_applied', round($all->whereIn('status', ['APPROVED', 'APPLIED'])->sum('amount'), 2), 'money')],
            'columns' => self::cols(['ref', 'raised:date', 'code', 'name', 'department', 'period:text:pay_period', 'kind:text:adjustment', 'original:money', 'revised:money', 'amount:money:adjustment_amount', 'status', 'paid_in']),
            'rows' => $rows, 'foot' => $rows ? ['ref' => __('hrpayroll.rp.total'), 'amount' => round(array_sum(array_column($rows, 'amount')), 2)] : null, 'empty' => __('hrpayroll.rp.e.no_adjustment'),
        ];
    }

    /* ── HR ────────────────────────────────────────────────────────── */

    private function leave(array $f): array
    {
        $people = $this->people($f)->get()->keyBy('id');
        $all = DB::table('hr_leave_applications as l')->leftJoin('hr_leave_types as t', 't.id', '=', 'l.leave_type_id')->where('l.company_id', $this->companyId)->whereIn('l.employee_id', $people->keys())
            ->where('l.from_date', '<=', $f['to'])->where('l.to_date', '>=', $f['from'])->orderBy('l.from_date')->get(['l.*', 't.name as type', 't.code as type_code']);
        $rows = $all->when(! empty($f['status']), fn ($c) => $c->where('status', $f['status']))->map(function ($l) use ($people) {
            $e = $people[$l->employee_id];
            $days = $l->mode === 'DAILY' ? (float) $l->days : round((float) $l->hours / 8, 2);

            return ['ref' => $l->ref_no, 'code' => $e->employee_code, 'name' => $e->name, 'designation' => $e->designation, 'department' => $e->department, 'type' => trim("{$l->type} ({$l->type_code})"),
                'from' => self::d($l->from_date), 'to' => self::d($l->to_date), 'applied' => $days, 'approved' => $l->status === 'APPROVED' ? $days : 0.0, 'apply_date' => self::d($l->apply_date),
                'approve_date' => self::d($l->approve_date), 'status' => self::s($l->status)];
        })->values()->all();

        return [
            'tiles' => [self::tile('t.applications', $all->count()), self::tile('s.APPROVED', $all->where('status', 'APPROVED')->count()), self::tile('s.PENDING', $all->where('status', 'PENDING')->count())],
            'columns' => self::cols(['ref', 'code', 'name', 'designation', 'department', 'type:text:leave_type', 'from:date:from_date', 'to:date:to_date', 'applied:num:days_applied', 'approved:num:days_approved', 'apply_date:date', 'approve_date:date', 'status']),
            'rows' => $rows, 'foot' => $rows ? ['ref' => __('hrpayroll.rp.total')] + self::sum($rows, ['applied', 'approved']) : null, 'empty' => __('hrpayroll.rp.e.widen'),
        ];
    }

    private function leaveBalance(array $f): array
    {
        $year = DB::table('acc_fiscal_years')->where('company_id', $this->companyId)->where('id', $f['fiscalYear'] ?? '')->first();
        $cols = self::cols(['code', 'name', 'designation', 'department', 'type:text:leave_type', 'entitled:num:entitlement', 'taken:num', 'adjusted:num', 'balance:num']);
        if (! $year) {
            return ['columns' => $cols, 'rows' => [], 'empty' => __('hrpayroll.rp.e.widen')];
        }
        $book = new LeaveBook($this->companyId);
        $rows = [];
        foreach ($this->people($f)->where('e.status', 'ACTIVE')->limit(self::MAX_ROWS)->get() as $e) {
            foreach ($book->balances($e, $year) as $b) {
                $taken = round((float) $b['taken_day'] + (float) $b['taken_hour'] / 8, 2);
                $rows[] = ['code' => $e->employee_code, 'name' => $e->name, 'designation' => $e->designation, 'department' => $e->department, 'type' => $b['leave_type'], 'entitled' => $b['entitled_day'],
                    'taken' => $taken, 'adjusted' => (float) $b['adjusted_day'], 'balance' => $b['balance_day'], '_e' => $e->id];
            }
        }

        return [
            'tiles' => [self::tile('t.employees', count(array_unique(array_column($rows, '_e')))), self::tile('t.days_taken', round(array_sum(array_column($rows, 'taken')), 2), 'num'),
                self::tile('t.days_remaining', round(array_sum(array_map(fn ($r) => (float) ($r['balance'] ?? 0), $rows)), 2), 'num')],
            'columns' => $cols, 'rows' => $rows, 'empty' => __('hrpayroll.rp.e.widen'),
        ];
    }

    private function promotions(array $f): array
    {
        $people = $this->people($f)->get()->keyBy('id');
        $desig = DB::table('designations')->where('company_id', $this->companyId)->pluck('name', 'id');
        $rows = collect();
        foreach (DB::table('hr_promotions')->where('company_id', $this->companyId)->whereIn('employee_id', $people->keys())->whereBetween('effective_date', [$f['from'], $f['to']])->get() as $p) {
            $rows->push(['_k' => 'PROMOTION', '_s' => $p->status, 'date' => self::d($p->effective_date), 'type' => self::s('PROMOTION'), 'ref' => $p->ref_no, 'e' => $p->employee_id,
                'old_designation' => $desig[$p->old_designation_id] ?? null, 'new_designation' => $desig[$p->new_designation_id] ?? null, 'old_gross' => self::f($p->old_gross), 'new_gross' => self::f($p->new_gross), 'reason' => $p->reason]);
        }
        foreach (DB::table('hr_increments')->where('company_id', $this->companyId)->whereIn('employee_id', $people->keys())->whereBetween('effective_date', [$f['from'], $f['to']])->get() as $i) {
            $e = $people[$i->employee_id];
            $rows->push(['_k' => 'INCREMENT', '_s' => $i->status, 'date' => self::d($i->effective_date), 'type' => self::s('INCREMENT'), 'ref' => $i->ref_no, 'e' => $i->employee_id,
                'old_designation' => $e->designation, 'new_designation' => $e->designation, 'old_gross' => self::f($i->old_gross), 'new_gross' => self::f($i->new_gross), 'reason' => $i->reason]);
        }
        $all = $rows;
        $rows = $rows->when(! empty($f['status']), fn ($c) => $c->where('_s', $f['status']))->sortBy(fn ($r) => $r['date'].$r['ref'])->map(function ($r) use ($people) {
            $e = $people[$r['e']];
            $inc = $r['new_gross'] !== null && $r['old_gross'] !== null ? round($r['new_gross'] - $r['old_gross'], 2) : null;

            return ['date' => $r['date'], 'type' => $r['type'], 'ref' => $r['ref'], 'code' => $e->employee_code, 'name' => $e->name, 'department' => $e->department, 'old_designation' => $r['old_designation'],
                'new_designation' => $r['new_designation'], 'old_gross' => $r['old_gross'], 'new_gross' => $r['new_gross'], 'increase' => $inc, 'status' => self::s($r['_s']), 'reason' => $r['reason'], '_s' => $r['_s']];
        })->values();
        $approvedIncrease = $all->where('_s', 'APPROVED')->sum(fn ($r) => $r['new_gross'] !== null && $r['old_gross'] !== null ? $r['new_gross'] - $r['old_gross'] : 0);

        return [
            'tiles' => [self::tile('t.promotions', $all->where('_k', 'PROMOTION')->count()), self::tile('t.increments', $all->where('_k', 'INCREMENT')->count()), self::tile('t.total_increase', round($approvedIncrease, 2), 'money')],
            'columns' => self::cols(['date:date:effective_date', 'type', 'ref', 'code', 'name', 'department', 'old_designation', 'new_designation', 'old_gross:money', 'new_gross:money', 'increase:money', 'status', 'reason']),
            'rows' => $rows->all(), 'empty' => __('hrpayroll.rp.e.widen'),
        ];
    }

    private function transfers(array $f): array
    {
        $people = $this->people($f)->get()->keyBy('id');
        $names = fn (string $t) => DB::table($t)->where('company_id', $this->companyId)->pluck('name', 'id');
        [$dept, $sect, $floor] = [$names('departments'), $names('sections'), $names('floors')];
        $lines = DB::table('production_lines')->where('company_id', $this->companyId)->get(['id', 'code', 'name'])->keyBy('id');
        $line = fn ($id) => $id && isset($lines[$id]) ? self::lineLabel($lines[$id]->code, $lines[$id]->name) : null;
        $all = DB::table('hr_transfers')->where('company_id', $this->companyId)->whereIn('employee_id', $people->keys())->whereBetween('effective_date', [$f['from'], $f['to']])->orderBy('effective_date')->orderBy('ref_no')->get();
        $rows = $all->when(! empty($f['status']), fn ($c) => $c->where('status', $f['status']))->map(function ($t) use ($people, $dept, $sect, $floor, $line) {
            $e = $people[$t->employee_id];

            return ['date' => self::d($t->effective_date), 'ref' => $t->ref_no, 'code' => $e->employee_code, 'name' => $e->name, 'old_department' => $dept[$t->old_department_id] ?? null, 'old_section' => $sect[$t->old_section_id] ?? null,
                'old_line' => $line($t->old_line_id), 'old_floor' => $floor[$t->old_floor_id] ?? null, 'new_department' => $dept[$t->new_department_id] ?? null, 'new_section' => $sect[$t->new_section_id] ?? null,
                'new_line' => $line($t->new_line_id), 'new_floor' => $floor[$t->new_floor_id] ?? null, 'status' => self::s($t->status), 'reason' => $t->reason];
        })->values()->all();

        return [
            'tiles' => [self::tile('t.transfers', $all->count()), self::tile('s.FINALIZED', $all->where('status', 'FINALIZED')->count()), self::tile('s.DRAFT', $all->where('status', 'DRAFT')->count())],
            'columns' => self::cols(['date:date:effective_date', 'ref', 'code', 'name', 'old_department', 'old_section', 'old_line', 'old_floor', 'new_department', 'new_section', 'new_line', 'new_floor', 'status', 'reason']),
            'rows' => $rows, 'empty' => __('hrpayroll.rp.e.widen'),
        ];
    }

    private function settlements(array $f): array
    {
        $people = $this->people($f)->get()->keyBy('id');
        $all = DB::table('hr_settlements as s')->leftJoin('hr_separations as p', 'p.id', '=', 's.separation_id')->leftJoin('hr_lefty_cases as l', 'l.id', '=', 's.lefty_id')
            ->where('s.company_id', $this->companyId)->whereIn('s.employee_id', $people->keys())->whereBetween('s.action_date', [$f['from'], $f['to']])->orderBy('s.action_date')
            ->get(['s.*', 'p.ref_no as sep_ref', 'p.type as sep_type', 'l.ref_no as lefty_ref']);
        $rows = $all->when(! empty($f['status']), fn ($c) => $c->where('status', $f['status']))->map(function ($s) use ($people) {
            $e = $people[$s->employee_id];
            $service = $e->joining_date ? CarbonImmutable::parse($e->joining_date)->diffInDays(CarbonImmutable::parse($s->action_date)) + 1 : null;

            return ['code' => $e->employee_code, 'name' => $e->name, 'department' => $e->department,
                'separation' => $s->sep_ref ? "{$s->sep_ref} (".self::s($s->sep_type).')' : ($s->lefty_ref ? "{$s->lefty_ref} (".__('hrpayroll.rp.lefty').')' : null),
                'type' => self::s($s->settlement_type), 'action_date' => self::d($s->action_date), 'service_days' => $service === null ? null : (int) $service, 'salary_due' => (float) $s->salary_due, 'ot_due' => (float) $s->ot_due,
                'bonus_due' => (float) $s->bonus_due, 'leave' => (float) $s->leave_encashment, 'deduction' => (float) $s->deduction, 'loan' => (float) $s->loan_recovery, 'other' => (float) $s->other_adjustment,
                'payable' => (float) $s->payable, 'status' => self::s($s->status)];
        })->values()->all();
        $nums = ['salary_due', 'ot_due', 'bonus_due', 'leave', 'deduction', 'loan', 'other', 'payable'];

        return [
            'tiles' => [self::tile('t.settlements', $all->count()), self::tile('t.final_payable', round($all->sum('payable'), 2), 'money')],
            'columns' => self::cols(['code', 'name', 'department', 'separation', 'type:text:settlement_type', 'action_date:date', 'service_days:int:service_days', 'salary_due:money', 'ot_due:money', 'bonus_due:money',
                'leave:money:leave_encash', 'deduction:money', 'loan:money:loan_recovery', 'other:money:other_adj', 'payable:money:final_payable', 'status']),
            'rows' => $rows, 'foot' => $rows ? self::sum($rows, $nums) + ['code' => __('hrpayroll.rp.total'), 'name' => __('hrpayroll.rp.n_employees', ['n' => count($rows)])] : null, 'empty' => __('hrpayroll.rp.e.widen'),
        ];
    }

    /* ── Shared ────────────────────────────────────────────────────── */

    /** Employees with their posting by name, narrowed by the placement filters and the employee box. */
    public function people(array $f): Builder
    {
        $q = DB::table('hr_employees as e')
            ->leftJoin('departments as dp', 'dp.id', '=', 'e.department_id')->leftJoin('sections as sc', 'sc.id', '=', 'e.section_id')
            ->leftJoin('production_lines as ln', 'ln.id', '=', 'e.line_id')->leftJoin('floors as fl', 'fl.id', '=', 'e.floor_id')->leftJoin('buildings as bd', 'bd.id', '=', 'e.building_id')
            ->leftJoin('designations as dg', 'dg.id', '=', 'e.designation_id')->leftJoin('grades as gr', 'gr.id', '=', 'e.grade_id')->leftJoin('hr_shifts as sh', 'sh.id', '=', 'e.shift_id')
            ->leftJoin('hr_employment_types as et', 'et.id', '=', 'e.employment_type_id')->leftJoin('hr_gazettes as gz', 'gz.id', '=', 'e.gazette_id')
            ->where('e.company_id', $this->companyId)->orderBy('e.employee_code')
            ->select(['e.*', 'dp.name as department', 'sc.name as section', 'ln.name as line', 'ln.code as line_code', 'fl.name as floor', 'bd.name as building', 'dg.name as designation',
                DB::raw('coalesce(gr.name, gr.code) as grade'), 'sh.name as shift', 'et.name as type_name', 'gz.name as gazette']);
        foreach (['department' => 'department_id', 'section' => 'section_id', 'line' => 'line_id', 'floor' => 'floor_id', 'designation' => 'designation_id'] as $k => $col) {
            if (! empty($f[$k])) {
                $q->where("e.{$col}", $f[$k]);
            }
        }
        $term = Str::lower(trim((string) ($f['employee'] ?? '')));
        if ($term !== '') {
            $q->where(fn ($w) => $w->whereRaw('lower(e.employee_code) = ?', [$term])->orWhereRaw('lower(e.card_no) = ?', [$term])->orWhereRaw('lower(e.name) like ?', ['%'.addcslashes($term, '%_\\').'%']));
        }

        return $q;
    }

    /** Operator, Quality, Helper, Supervisor or Staff: the designation group of the same name, else by the designation's name, else by employment type. */
    public function category(object $e): string
    {
        if ($this->categoryMap === null) {
            $groups = DB::table('hr_designation_groups')->where('company_id', $this->companyId)->whereNotNull('manpower_category')->get(['name', 'manpower_category'])
                ->mapWithKeys(fn ($g) => [Str::lower(trim($g->name)) => $g->manpower_category])->all();
            $this->categoryMap = DB::table('designations')->where('company_id', $this->companyId)->get(['id', 'name'])
                ->mapWithKeys(fn ($d) => [$d->id => $groups[Str::lower(trim((string) $d->name))] ?? self::guess((string) $d->name)])->filter()->all();
        }

        return $this->categoryMap[$e->designation_id ?? ''] ?? (Str::lower((string) ($e->type_name ?? '')) === 'worker' ? 'OPERATOR' : 'STAFF');
    }

    private static function guess(string $name): ?string
    {
        $n = Str::lower($name);

        return match (true) {
            str_contains($n, 'helper') => 'HELPER',
            (bool) preg_match('/\b(quality|qc|qa|checker|inspector|auditor)\b/', $n) => 'QUALITY',
            (bool) preg_match('/supervisor|line chief|in-?charge|chief/', $n) => 'SUPERVISOR',
            (bool) preg_match('/operator|machinist|iron|cutter|folder|packer|knitter|dyer/', $n) => 'OPERATOR',
            default => null,
        };
    }

    /** A day's attendance as the manpower reports count it. */
    public static function state(?object $d): string
    {
        return match ($d->status ?? null) {
            'PRESENT', 'MISSING_PUNCH' => 'PRESENT',
            'LEAVE' => 'LEAVE',
            'WEEKLY_OFF', 'HOLIDAY' => 'OFF',
            default => 'ABSENT',
        };
    }

    private function salary(): SalaryBook
    {
        return $this->salaryBook ??= new SalaryBook($this->companyId);
    }

    /** 'code:kind:label' => column; kind defaults to text and the label to the key. @return list<array<string, string>> */
    private static function cols(array $specs): array
    {
        return array_map(function (string $s) {
            $p = explode(':', $s);

            return ['key' => $p[0], 'label' => __('hrpayroll.rp.c.'.($p[2] ?? $p[0])), 'kind' => $p[1] ?? 'text'];
        }, $specs);
    }

    /** @return array{label: string, value: mixed, kind: string} */
    private static function tile(string $key, mixed $value, string $kind = 'int'): array
    {
        return ['label' => __("hrpayroll.rp.{$key}"), 'value' => is_float($value) ? round($value, 2) : $value, 'kind' => $kind];
    }

    /** @param list<array<string, mixed>> $rows */
    private static function sum(array $rows, array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = round(array_sum(array_map(fn ($r) => (float) ($r[$k] ?? 0), $rows)), 2);
        }

        return $out;
    }

    public static function s(?string $status): ?string
    {
        if ($status === null || $status === '') {
            return null;
        }
        $key = "hrpayroll.rp.s.{$status}";
        $t = __($key);

        return $t === $key ? Str::headline(Str::lower($status)) : $t;
    }

    private static function label(?string $v): ?string
    {
        return $v ? Str::headline(Str::lower($v)) : null;
    }

    public static function lineLabel(?string $code, ?string $name): ?string
    {
        return $code || $name ? implode(' · ', array_filter([$code, $name])) : null;
    }

    private static function shiftLabel(?object $s): ?string
    {
        return $s ? $s->name.' ('.Clock::range(substr((string) $s->start_time, 0, 5), substr((string) $s->end_time, 0, 5)).')' : null;
    }

    private static function d(mixed $v): ?string
    {
        return $v ? substr((string) $v, 0, 10) : null;
    }

    private static function localDate(mixed $at): ?string
    {
        return $at ? CarbonImmutable::parse((string) $at, 'UTC')->timezone(self::TZ)->toDateString() : null;
    }

    private static function hm(mixed $at): ?string
    {
        return $at ? CarbonImmutable::parse((string) $at)->timezone(self::TZ)->format('h:i A') : null;
    }

    private static function f(mixed $v): ?float
    {
        return $v === null ? null : (float) $v;
    }

    private static function n(mixed $v): string
    {
        return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    }

    private static function money(mixed $v): string
    {
        return $v === null ? '—' : number_format((float) $v, 2);
    }

    public static function dmy(mixed $v): string
    {
        return $v ? CarbonImmutable::parse(substr((string) $v, 0, 10))->format('d M Y') : '';
    }

    /** @return array{0: string, 1: string} */
    private static function month(string $ym): array
    {
        $m = CarbonImmutable::createFromFormat('Y-m-d', $ym.'-01', self::TZ) ?: CarbonImmutable::now(self::TZ);

        return [$m->startOfMonth()->toDateString(), $m->endOfMonth()->toDateString()];
    }
}
