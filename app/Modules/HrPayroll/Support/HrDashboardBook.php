<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * HR & Payroll → Dashboard (AnnotechERP 24/02), as Brickland lays it out:
 * the head-count and today's attendance tiles, employees by department, the
 * month's attendance by day, the month's payroll; recent joiners, pending
 * separations, recent lefty cases and the latest notifications; then
 * "Today's Operations" — manpower by category and by line, the attendance
 * summary, OT by line, what waits for approval and the recent activity.
 *
 * "This Month" counts Today's Operations over the month to the chosen day
 * (employee-days); the tiles above stay on the day.
 */
class HrDashboardBook
{
    private const TZ = HrReportBook::TZ;

    private HrReportBook $reports;

    public function __construct(private readonly string $companyId)
    {
        $this->reports = new HrReportBook($companyId);
    }

    /** @return array<string, mixed> */
    public function data(string $date, string $period, ?string $department, bool $salary, ?string $userId): array
    {
        $c = $this->companyId;
        $day = CarbonImmutable::parse($date, self::TZ);
        [$monthStart, $monthEnd] = [$day->startOfMonth()->toDateString(), $day->endOfMonth()->toDateString()];
        $from = $period === 'MONTH' ? $monthStart : $date;

        $all = DB::table('hr_employees')->where('company_id', $c)->get(['id', 'status', 'joining_date', 'separation_date', 'created_at']);
        $atStart = $all->filter(fn ($e) => ! $e->joining_date || substr((string) $e->joining_date, 0, 10) < $monthStart)->count();
        $active = $this->reports->manpower(['date' => $date]);
        $present = $active->where('state', 'PRESENT')->count();
        $absent = $active->where('state', 'ABSENT')->count();
        $notRecorded = $active->filter(fn ($e) => $e->day === null)->count();
        $n = max(1, $active->count());

        $periodRow = DB::table('hr_pay_periods')->where('company_id', $c)->where('start_date', '<=', $date)->where('end_date', '>=', $date)->first();
        $payroll = null;
        if ($salary && $periodRow) {
            $slips = DB::table('hr_salary_slips')->where('company_id', $c)->where('pay_period_id', $periodRow->id)->get();
            $run = DB::table('hr_salary_runs')->where('company_id', $c)->where('pay_period_id', $periodRow->id)->first();
            if ($slips->isNotEmpty()) {
                $payroll = ['period' => $periodRow->period_no, 'status' => HrReportBook::s($run?->status), 'employees' => $slips->count(), 'gross' => round($slips->sum('earned_gross'), 2),
                    'ot' => round($slips->sum('ot_amount'), 2), 'bonus' => round($slips->sum('bonus'), 2),
                    'deductions' => round($slips->sum(fn ($s) => (float) $s->late_deduction + (float) $s->stamp_charge + (float) $s->other_deduction + (float) $s->loan_advance), 2), 'net' => round($slips->sum('net'), 2)];
            }
        }
        $monthOt = $this->otDays($monthStart, min($monthEnd, $date));

        return [
            'date' => $date, 'period' => $period, 'monthLabel' => $day->format('F Y'),
            'kpis' => [
                'total' => $all->count(), 'totalDelta' => $atStart ? round(($all->count() - $atStart) * 100 / $atStart, 2) : null,
                'active' => $all->where('status', 'ACTIVE')->count(), 'activePct' => $all->count() ? round($all->where('status', 'ACTIVE')->count() * 100 / $all->count(), 1) : 0.0,
                'inactive' => $all->whereIn('status', ['INACTIVE', 'SUSPENDED'])->count(), 'resigned' => $all->where('status', 'SEPARATED')->count(),
                'pendingSeparation' => DB::table('hr_separations')->where('company_id', $c)->where('status', 'PENDING')->count(),
                'lefty' => DB::table('hr_lefty_cases')->where('company_id', $c)->where('status', 'CONFIRMED')->count(),
                'present' => $present, 'presentPct' => round($present * 100 / $n, 1), 'late' => $active->where('late', true)->count(),
                'absent' => $absent, 'absentPct' => round($absent * 100 / $n, 1), 'missingPunch' => $active->where('missing', true)->count(),
                'pendingOt' => DB::table('hr_ot_requests')->where('company_id', $c)->where('status', 'PENDING')->count(),
                'approvedOtHours' => round($monthOt->sum('hours'), 2), 'netPayable' => $payroll['net'] ?? null,
            ],
            'byDepartment' => $this->byDepartment($active),
            'attendanceDays' => $this->attendanceDays($monthStart, $monthEnd),
            'payroll' => $payroll, 'canSalary' => $salary,
            'recentEmployees' => $this->recentEmployees(),
            'pendingSeparations' => $this->pendingSeparations(),
            'recentLefty' => $this->recentLefty(),
            'notifications' => $userId ? DB::table('notifications')->where('company_id', $c)->where('user_id', $userId)->latest('created_at')->limit(6)->get(['title', 'body', 'created_at', 'read_at'])
                ->map(fn ($x) => ['message' => trim($x->title.($x->body ? ' — '.$x->body : '')), 'at' => (string) $x->created_at, 'unread' => $x->read_at === null])->values()->all() : [],
            'operations' => $this->operations($from, $date, $department, $notRecorded),
            'pending' => $this->pending(),
            'activities' => $this->activities(),
        ];
    }

    /** @return list<array{label: string, value: int}> */
    private function byDepartment(Collection $active): array
    {
        $groups = $active->groupBy(fn ($e) => $e->department ?? __('hrpayroll.rp.none'))->map->count()->sortDesc();
        $top = $groups->take(5);
        $rest = $groups->slice(5)->sum();
        $out = $top->map(fn ($v, $k) => ['label' => (string) $k, 'value' => $v])->values()->all();
        if ($rest > 0) {
            $out[] = ['label' => __('hrpayroll.rp.db.others'), 'value' => $rest];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function attendanceDays(string $from, string $to): array
    {
        $rows = DB::table('hr_attendance_days')->where('company_id', $this->companyId)->whereBetween('date', [$from, $to])
            ->selectRaw("date, sum(case when status in ('PRESENT','MISSING_PUNCH') then 1 else 0 end) as present, sum(case when status = 'ABSENT' then 1 else 0 end) as absent, sum(case when is_late then 1 else 0 end) as late, sum(case when status = 'LEAVE' then 1 else 0 end) as leave")
            ->groupBy('date')->get()->keyBy(fn ($r) => substr((string) $r->date, 0, 10));
        $out = [];
        for ($d = CarbonImmutable::parse($from); $d->toDateString() <= $to; $d = $d->addDay()) {
            $r = $rows[$d->toDateString()] ?? null;
            $out[] = ['date' => $d->toDateString(), 'present' => (int) ($r->present ?? 0), 'absent' => (int) ($r->absent ?? 0), 'late' => (int) ($r->late ?? 0), 'leave' => (int) ($r->leave ?? 0)];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function recentEmployees(): array
    {
        return DB::table('hr_employees as e')->leftJoin('departments as d', 'd.id', '=', 'e.department_id')->leftJoin('designations as g', 'g.id', '=', 'e.designation_id')
            ->where('e.company_id', $this->companyId)->orderByDesc('e.joining_date')->orderByDesc('e.created_at')->limit(5)
            ->get(['e.employee_code', 'e.name', 'e.card_no', 'd.name as department', 'g.name as designation', 'e.joining_date', 'e.status'])
            ->map(fn ($e) => ['code' => $e->employee_code, 'name' => $e->name, 'card' => $e->card_no, 'department' => $e->department, 'designation' => $e->designation,
                'joining' => $e->joining_date ? substr((string) $e->joining_date, 0, 10) : null, 'status' => $e->status])->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function pendingSeparations(): array
    {
        return DB::table('hr_separations as s')->join('hr_employees as e', 'e.id', '=', 's.employee_id')->where('s.company_id', $this->companyId)->where('s.status', 'PENDING')
            ->orderBy('s.application_date')->limit(5)->get(['s.ref_no', 's.type', 's.application_date', 's.status', 'e.employee_code', 'e.name'])
            ->map(fn ($s) => ['type' => $s->type, 'ref' => $s->ref_no, 'employee' => "{$s->employee_code} {$s->name}", 'date' => substr((string) $s->application_date, 0, 10), 'status' => $s->status])->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function recentLefty(): array
    {
        return DB::table('hr_lefty_cases as l')->join('hr_employees as e', 'e.id', '=', 'l.employee_id')->leftJoin('departments as d', 'd.id', '=', 'e.department_id')
            ->where('l.company_id', $this->companyId)->orderByDesc('l.created_at')->orderByDesc('l.ref_no')->limit(5)
            ->get(['l.ref_no', 'l.last_attendance_date', 'l.status', 'e.name', 'e.card_no', 'd.name as department'])
            ->map(fn ($l) => ['ref' => $l->ref_no, 'name' => $l->name, 'card' => $l->card_no, 'department' => $l->department, 'last' => $l->last_attendance_date ? substr((string) $l->last_attendance_date, 0, 10) : null, 'status' => $l->status])->values()->all();
    }

    /** Manpower by category and by line, the attendance summary and OT by line, for the day or the month to it. @return array<string, mixed> */
    private function operations(string $from, string $to, ?string $department, int $notRecorded): array
    {
        $people = $this->reports->people([])->where('e.status', 'ACTIVE')->get()->keyBy('id');
        foreach ($people as $e) {
            $e->category = $this->reports->category($e);
        }
        $days = DB::table('hr_attendance_days')->where('company_id', $this->companyId)->whereBetween('date', [$from, $to])->whereIn('employee_id', $people->keys())->get();
        $span = CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) + 1;
        // Each employee-day once: recorded days as they stand, unrecorded days as absent (not punched yet).
        $cells = collect();
        $seen = [];
        foreach ($days as $d) {
            $seen[$d->employee_id.'|'.substr((string) $d->date, 0, 10)] = true;
            $cells->push(['e' => $people[$d->employee_id], 'state' => HrReportBook::state($d), 'late' => (bool) $d->is_late, 'missing' => $d->status === 'MISSING_PUNCH', 'none' => false]);
        }
        $missingDays = $people->count() * (int) $span - count($seen);
        foreach ($people as $e) {
            $joined = $e->joining_date ? substr((string) $e->joining_date, 0, 10) : '';
            for ($d = CarbonImmutable::parse($from); $d->toDateString() <= $to && $missingDays > 0; $d = $d->addDay()) {
                if ($d->toDateString() >= $joined && ! isset($seen[$e->id.'|'.$d->toDateString()])) {
                    $cells->push(['e' => $e, 'state' => 'ABSENT', 'late' => false, 'missing' => false, 'none' => true]);
                    $missingDays--;
                }
            }
        }
        $ot = $this->otDays($from, $to);
        $designation = array_map(fn ($cat) => ['category' => __("hrpayroll.rp.s.{$cat}"), 'total' => $cells->filter(fn ($x) => $x['e']->category === $cat)->count(),
            'present' => $cells->filter(fn ($x) => $x['e']->category === $cat && $x['state'] === 'PRESENT')->count(),
            'absent' => $cells->filter(fn ($x) => $x['e']->category === $cat && $x['state'] === 'ABSENT')->count()], HrReportBook::MANPOWER);
        $deptCells = $cells->filter(fn ($x) => ! $department || $x['e']->department_id === $department);
        $otByEmp = $ot->groupBy(fn ($d) => $d['e']->id);
        $lines = $deptCells->groupBy(fn ($x) => HrReportBook::lineLabel($x['e']->line_code, $x['e']->line) ?? '')->map(function ($g, $name) use ($otByEmp) {
            $ids = $g->pluck('e.id')->unique();

            return ['line' => $name === '' ? __('hrpayroll.rp.no_line') : $name, 'total' => $g->count(), 'present' => $g->where('state', 'PRESENT')->count(), 'absent' => $g->where('state', 'ABSENT')->count(),
                'otEmployees' => $ids->filter(fn ($id) => isset($otByEmp[$id]))->count(), 'otHours' => round($ids->sum(fn ($id) => isset($otByEmp[$id]) ? $otByEmp[$id]->sum('hours') : 0), 2)];
        })->sortBy(fn ($r) => ($r['line'] === __('hrpayroll.rp.no_line') ? 'zz' : 'a').$r['line'])->values()->all();
        $topOt = $ot->groupBy(fn ($d) => HrReportBook::lineLabel($d['e']->line_code, $d['e']->line) ?? __('hrpayroll.rp.no_line'))
            ->map(fn ($g, $name) => ['line' => $name, 'employees' => $g->unique(fn ($d) => $d['e']->id)->count(), 'hours' => round($g->sum('hours'), 2), 'amount' => round($g->sum('amount'), 2)])
            ->sortByDesc('hours')->take(5)->values()->all();
        $presentOnTime = $cells->filter(fn ($x) => $x['state'] === 'PRESENT' && ! $x['late'] && ! $x['missing'])->count();
        $summary = [['key' => 'PRESENT', 'value' => $presentOnTime], ['key' => 'LATE', 'value' => $cells->filter(fn ($x) => $x['state'] === 'PRESENT' && $x['late'])->count()],
            ['key' => 'MISSING_PUNCH', 'value' => $cells->filter(fn ($x) => $x['missing'] && ! $x['late'])->count()], ['key' => 'LEAVE', 'value' => $cells->where('state', 'LEAVE')->count()],
            ['key' => 'ABSENT', 'value' => $cells->filter(fn ($x) => $x['state'] === 'ABSENT' && ! $x['none'])->count()], ['key' => 'NOT_RECORDED', 'value' => $cells->where('none', true)->count()]];

        return [
            'from' => $from, 'to' => $to, 'designation' => $designation, 'lines' => $lines, 'otLines' => $topOt,
            'summary' => array_map(fn ($s) => $s + ['label' => __("hrpayroll.rp.db.att.{$s['key']}")], $summary), 'total' => $cells->count(), 'notRecordedToday' => $notRecorded,
            'departments' => DB::table('departments')->where('company_id', $this->companyId)->orderBy('name')->get(['id', 'name'])->map(fn ($d) => ['value' => $d->id, 'label' => $d->name])->values()->all(),
            'department' => $department,
        ];
    }

    /** OT days of active employees in a range (as the OT reports count them). */
    private function otDays(string $from, string $to): Collection
    {
        $people = $this->reports->people([])->where('e.status', 'ACTIVE')->get();

        return collect($this->reports->otDays($from, $to, $people));
    }

    /** What waits for approval: OT requests, leave applications, attendance corrections. @return array<string, list<array<string, mixed>>> */
    private function pending(): array
    {
        $c = $this->companyId;

        return [
            'ot' => DB::table('hr_ot_requests as r')->leftJoin('departments as d', 'd.id', '=', 'r.department_id')->leftJoin('production_lines as l', 'l.id', '=', 'r.line_id')
                ->where('r.company_id', $c)->where('r.status', 'PENDING')->orderBy('r.date')->limit(8)->get(['r.request_no', 'r.date', 'r.ot_start', 'r.ot_end', 'd.name as department', 'l.code as line', 'r.reason'])
                ->map(fn ($r) => ['ref' => $r->request_no, 'date' => substr((string) $r->date, 0, 10), 'detail' => trim(($r->department ?? '').($r->line ? " · {$r->line}" : '').' · '.substr((string) $r->ot_start, 0, 5).'–'.substr((string) $r->ot_end, 0, 5)), 'reason' => $r->reason])->values()->all(),
            'leave' => DB::table('hr_leave_applications as l')->join('hr_employees as e', 'e.id', '=', 'l.employee_id')->leftJoin('hr_leave_types as t', 't.id', '=', 'l.leave_type_id')
                ->where('l.company_id', $c)->where('l.status', 'PENDING')->orderBy('l.from_date')->limit(8)->get(['l.ref_no', 'l.from_date', 'l.to_date', 'l.days', 'e.employee_code', 'e.name', 't.name as type'])
                ->map(fn ($l) => ['ref' => $l->ref_no, 'employee' => "{$l->employee_code} {$l->name}", 'type' => $l->type, 'from' => substr((string) $l->from_date, 0, 10), 'to' => substr((string) $l->to_date, 0, 10), 'days' => (float) $l->days])->values()->all(),
            'attendance' => DB::table('hr_attendance_corrections as k')->join('hr_employees as e', 'e.id', '=', 'k.employee_id')->leftJoin('users as u', 'u.id', '=', 'k.requested_by')
                ->where('k.company_id', $c)->where('k.status', 'PENDING')->orderBy('k.date')->limit(8)->get(['k.date', 'k.old_in', 'k.old_out', 'k.correct_in', 'k.correct_out', 'k.reason', 'e.employee_code', 'e.name', 'u.name as by'])
                ->map(fn ($k) => ['date' => substr((string) $k->date, 0, 10), 'employee' => $k->name, 'code' => $k->employee_code,
                    'change' => self::hm($k->old_in).'-'.self::hm($k->old_out).' → '.self::hm($k->correct_in).'-'.self::hm($k->correct_out), 'reason' => $k->reason, 'requestedBy' => $k->by])->values()->all(),
        ];
    }

    /** Device syncs and new employees, newest first. @return list<array<string, mixed>> */
    private function activities(): array
    {
        $syncs = DB::table('dev_punches as p')->join('dev_devices as d', 'd.id', '=', 'p.device_id')->where('p.company_id', $this->companyId)
            ->selectRaw("d.name as device, date_trunc('minute', p.received_at) as at, count(*) as n")->groupBy('d.name', DB::raw("date_trunc('minute', p.received_at)"))
            ->orderByDesc('at')->limit(8)->get()->map(fn ($s) => ['kind' => 'DEVICE', 'title' => __('hrpayroll.rp.db.synced'), 'detail' => __('hrpayroll.rp.db.sync_detail', ['device' => $s->device, 'n' => $s->n]), 'at' => (string) $s->at]);
        $joins = DB::table('hr_employees')->where('company_id', $this->companyId)->orderByDesc('created_at')->limit(8)->get(['employee_code', 'name', 'card_no', 'created_at'])
            ->map(fn ($e) => ['kind' => 'EMPLOYEE', 'title' => __('hrpayroll.rp.db.registered'), 'detail' => "{$e->name} ({$e->employee_code}) · ".__('hrpayroll.rp.db.card', ['card' => $e->card_no]), 'at' => (string) $e->created_at]);

        return $syncs->concat($joins)->sortByDesc('at')->take(8)->map(fn ($a) => $a + ['at' => CarbonImmutable::parse($a['at'], 'UTC')->toIso8601String()])->values()->all();
    }

    private static function hm(mixed $t): string
    {
        return $t ? substr((string) $t, 0, 5) : '—';
    }
}
