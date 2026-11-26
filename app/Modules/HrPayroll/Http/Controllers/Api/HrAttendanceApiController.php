<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Http\Controllers\Api;

use App\Modules\AttendanceDevice\Support\Terminals;
use App\Modules\HrPayroll\Support\AttendanceBook;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Pdf\TablePdf;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\Response;

/**
 * HR & Payroll → Attendance & Shift (AnnotechERP 24/04), as Brickland lays it
 * out: Daily Attendance (the day's — or a range's — attendance with the
 * status tiles), Biometric Sync (the terminals, the day's raw punches, Sync
 * now, Import file), Missing Punch (days with IN or OUT missing, and their
 * corrections for approval), Attendance Correction (one employee's month on
 * one chart: status, IN, OUT, late and OT corrected, a day removed) and the
 * Monthly Attendance Summary (the month-end job card summary).
 *
 * The days are worked out from the punches (AttendanceBook) whenever a range
 * is looked at, so a screen never shows a stale day; a corrected day stays as
 * it was corrected.
 */
class HrAttendanceApiController extends ApiController
{
    private const MAX_DAYS = 62;

    private const TZ = 'Asia/Dhaka';

    public function options(): JsonResponse
    {
        $this->allowEither('hrpayroll.attendance.view', 'hrpayroll.attendance.manage', 'hrpayroll.attendance.approve');
        $c = $this->caller()->companyId;
        $today = CarbonImmutable::now(self::TZ);

        return ApiResponse::ok([
            'today' => $today->toDateString(), 'year' => (int) $today->year, 'month' => (int) $today->month,
            'departments' => DB::table('departments')->where('company_id', $c)->where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name'])->map(fn ($x) => ['value' => $x->id, 'label' => $x->name])->all(),
            'sections' => DB::table('sections')->where('company_id', $c)->where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name', 'department_id'])->map(fn ($x) => ['value' => $x->id, 'label' => $x->name, 'department_id' => $x->department_id])->all(),
            'lines' => DB::table('production_lines')->where('company_id', $c)->where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name', 'section_id', 'department_id'])->map(fn ($x) => ['value' => $x->id, 'label' => $x->name, 'section_id' => $x->section_id, 'department_id' => $x->department_id])->all(),
            'employees' => DB::table('hr_employees')->where('company_id', $c)->orderBy('employee_code')->get(['employee_code', 'name', 'card_no'])->map(fn ($e) => ['value' => $e->employee_code, 'label' => "{$e->employee_code} — {$e->name}", 'card_no' => $e->card_no])->all(),
            'statuses' => AttendanceBook::STATUSES,
            'devices' => DB::table('dev_devices')->where('company_id', $c)->orderBy('code')->get(['code', 'name'])->map(fn ($d) => ['value' => $d->code, 'label' => "{$d->code} — {$d->name}"])->all(),
        ]);
    }

    /* ── Daily Attendance ──────────────────────────────────────────── */

    public function daily(Request $request): JsonResponse
    {
        $this->allowEither('hrpayroll.attendance.view', 'hrpayroll.attendance.manage');
        [$from, $to, $f] = $this->range($request);
        $this->book()->process($from, $to);
        $rows = $this->days($from, $to, $f)->get();
        $counts = array_fill_keys(['PRESENT', 'ABSENT', 'LATE', 'EARLY_OUT', 'LEAVE', 'MISSING_PUNCH', 'HOLIDAY', 'WEEKLY_OFF'], 0);
        foreach ($rows as $r) {
            $counts[$r->status]++;
            $counts['LATE'] += $r->is_late ? 1 : 0;
            $counts['EARLY_OUT'] += $r->is_early_out ? 1 : 0;
        }
        $shifts = $this->book()->shifts();

        return ApiResponse::ok(['from' => $from, 'to' => $to, 'counts' => $counts, 'rows' => $rows->map(fn ($r) => $this->dayRow($r, $shifts))->values()->all()]);
    }

    /** Export CSV / PDF of the Daily Attendance as filtered. */
    public function dailyExport(Request $request, string $format): Response
    {
        $this->allowEither('hrpayroll.attendance.view', 'hrpayroll.attendance.manage');
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);
        [$from, $to, $f] = $this->range($request);
        $this->book()->process($from, $to);
        $shifts = $this->book()->shifts();
        $rows = $this->days($from, $to, $f)->get()->map(fn ($r) => $this->dayRow($r, $shifts))->filter(fn ($r) => empty($f['status']) || $this->hasStatus($r, $f['status']))->values();
        $columns = array_map(fn ($k) => ['label' => __("hrpayroll.att.col.{$k}"), 'numeric' => in_array($k, ['worked', 'late', 'early', 'ot'], true)], ['code', 'employee', 'date', 'shift', 'in', 'out', 'worked', 'late', 'early', 'ot', 'status']);
        $body = $rows->map(fn ($r) => [$r['code'], $r['name'], $r['date'], $r['shift'], $r['in'], $r['out'], $r['worked'], $r['late'] ?: null, $r['early'] ?: null, $r['ot'], __("hrpayroll.att.status.{$r['status']}").($r['is_late'] ? ' · '.__('hrpayroll.att.status.LATE') : '')])->all();
        $name = "daily-attendance-{$from}".($to !== $from ? "-{$to}" : '');
        $meta = [__('hrpayroll.att.f.from') => $from, __('hrpayroll.att.f.to') => $to, __('hrpayroll.att.rows') => (string) count($body)];

        return $this->file($format, __('hrpayroll.emp.screens.daily-attendance'), $columns, $body, $meta, null, $name);
    }

    /* ── Biometric Sync ────────────────────────────────────────────── */

    public function sync(Request $request): JsonResponse
    {
        $this->allowEither('hrpayroll.attendance.view', 'hrpayroll.attendance.manage');
        $c = $this->caller()->companyId;
        $f = $request->validate(['date' => ['nullable', 'date'], 'unmapped' => ['nullable', 'boolean']]);
        $date = $f['date'] ?? CarbonImmutable::now(self::TZ)->toDateString();
        [$a, $z] = [CarbonImmutable::parse($date, self::TZ)->startOfDay()->utc(), CarbonImmutable::parse($date, self::TZ)->endOfDay()->utc()];
        $devices = DB::table('dev_devices')->where('company_id', $c)->orderBy('code')->get();
        $today = DB::table('dev_punches')->where('company_id', $c)->whereBetween('punched_at', [$a, $z])->selectRaw('device_id, count(*) as n')->groupBy('device_id')->pluck('n', 'device_id');
        $lastSync = DB::table('dev_punches')->where('company_id', $c)->selectRaw('device_id, max(received_at) as at')->groupBy('device_id')->pluck('at', 'device_id');
        $users = DB::table('dev_users')->where('company_id', $c)->selectRaw('device_id, count(*) as n')->groupBy('device_id')->pluck('n', 'device_id');
        $fingers = DB::table('dev_fingerprints')->where('company_id', $c)->selectRaw('device_id, count(*) as n')->groupBy('device_id')->pluck('n', 'device_id');
        $punches = DB::table('dev_punches as p')->join('dev_devices as d', 'd.id', '=', 'p.device_id')->leftJoin('hr_employees as e', 'e.id', '=', 'p.employee_id')
            ->where('p.company_id', $c)->whereBetween('p.punched_at', [$a, $z])->when(! empty($f['unmapped']), fn ($q) => $q->whereNull('p.employee_id'))
            ->orderByDesc('p.punched_at')->limit(5000)->get(['p.id', 'p.pin', 'p.punched_at', 'p.employee_id', 'e.employee_code', 'e.name', 'd.code as device']);
        $all = DB::table('dev_punches')->where('company_id', $c)->whereBetween('punched_at', [$a, $z]);
        $registered = DB::table('dev_fingerprints')->where('company_id', $c)->whereNotNull('employee_id')->distinct()->count('employee_id');
        $pending = DB::table('dev_employee_maps')->where('company_id', $c)->where('status', 'PENDING')->whereNotIn('employee_id', DB::table('dev_fingerprints')->whereNotNull('employee_id')->select('employee_id'))->distinct()->count('employee_id');
        $active = DB::table('hr_employees')->where('company_id', $c)->where('status', '!=', 'SEPARATED')->count();

        return ApiResponse::ok([
            'date' => $date,
            'tiles' => ['devices' => $devices->count(), 'online' => $devices->filter(fn ($d) => $d->state === 'ACTIVE' && Terminals::online($d))->count(), 'punches' => (clone $all)->count(),
                'registered' => $registered, 'pending' => $pending, 'not_registered' => max(0, $active - $registered - $pending)],
            'unmapped' => (clone $all)->whereNull('employee_id')->count(),
            'devices' => $devices->map(fn ($d) => [
                'code' => $d->code, 'name' => $d->name, 'protocol' => $d->protocol, 'ip_address' => $d->ip_address, 'location' => $d->location, 'state' => $d->state,
                'online' => $d->state === 'ACTIVE' && Terminals::online($d), 'last_seen' => $this->iso($d->last_contact_at), 'last_sync' => $this->iso($lastSync[$d->id] ?? null),
                'punches' => (int) ($today[$d->id] ?? 0), 'users' => $d->user_count ?? (int) ($users[$d->id] ?? 0), 'fingers' => $d->fp_count ?? (int) ($fingers[$d->id] ?? 0),
            ])->values()->all(),
            'punches' => $punches->map(fn ($p) => ['id' => $p->id, 'time' => CarbonImmutable::parse($p->punched_at)->timezone(self::TZ)->format('H:i:s'), 'pin' => $p->pin,
                'employee' => $p->employee_id ? "{$p->name} ({$p->employee_code})" : null, 'device' => $p->device])->all(),
        ]);
    }

    /** Sync now: today's (and yesterday's, for night shifts) attendance worked out again from the punches in. */
    public function syncNow(): JsonResponse
    {
        $this->allow('hrpayroll.attendance.manage');
        $today = CarbonImmutable::now(self::TZ);
        $n = $this->book()->process($today->subDay()->toDateString(), $today->toDateString());

        return ApiResponse::ok(['message' => __('hrpayroll.att.done.synced', ['n' => $n])]);
    }

    /**
     * Import file: a terminal's attendance log taken off it on a USB stick
     * (attlog.dat — PIN, time, …, one punch a line). Punches already in are
     * skipped; the days touched are worked out again.
     */
    public function import(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.attendance.manage');
        $d = $request->validate(['device' => ['required', 'string'], 'upload' => ['required', 'file', 'max:20480']], ['device.required' => __('hrpayroll.att.v.device'), 'upload.required' => __('hrpayroll.att.v.file')]);
        $device = DB::table('dev_devices')->where('company_id', $this->caller()->companyId)->where('code', $d['device'])->first() ?? throw ValidationException::withMessages(['device' => __('hrpayroll.att.v.device')]);
        $now = now();
        $read = $saved = 0;
        $touched = [];
        $pins = [];
        $handle = fopen($request->file('upload')->getRealPath(), 'r');
        while (($line = fgets($handle)) !== false) {
            $f = preg_split('/\t|\s{2,}/', trim($line)) ?: [];
            // attlog.dat: PIN \t YYYY-MM-DD HH:MM:SS \t verify \t state \t …
            if (count($f) < 2 || ! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', trim($f[1]))) {
                continue;
            }
            $read++;
            $at = Terminals::moment(strlen(trim($f[1])) === 16 ? trim($f[1]).':00' : trim($f[1]), (float) $device->time_zone);
            if (! $at) {
                continue;
            }
            $pin = mb_substr(trim($f[0]), 0, 24);
            $employee = $pins[$pin] ??= Terminals::match($device, $pin);
            $n = DB::table('dev_punches')->insertOrIgnore(['id' => strtolower((string) Str::ulid()), 'company_id' => $device->company_id, 'device_id' => $device->id, 'pin' => $pin, 'punched_at' => $at,
                'state' => (int) ($f[3] ?? 0), 'verify' => (int) ($f[2] ?? 1), 'source' => 'FILE', 'employee_id' => $employee, 'posted_to_hr' => $employee !== null && $device->post_to_hr, 'received_at' => $now]);
            $saved += $n;
            if ($n && $employee) {
                $touched[$at->timezone(self::TZ)->toDateString()][$employee] = true;
            }
        }
        fclose($handle);
        if ($read === 0) {
            throw ValidationException::withMessages(['upload' => __('hrpayroll.att.v.file_format')]);
        }
        foreach ($touched as $date => $employees) {
            $this->book()->process($date, $date, array_keys($employees));
        }

        return ApiResponse::ok(['message' => __('hrpayroll.att.done.imported', ['read' => $read, 'saved' => $saved, 'days' => count($touched)])]);
    }

    /* ── Missing Punch ─────────────────────────────────────────────── */

    public function missing(Request $request): JsonResponse
    {
        $this->allowEither('hrpayroll.attendance.view', 'hrpayroll.attendance.manage', 'hrpayroll.attendance.approve');
        [$from, $to, $f] = $this->range($request, 6);
        $this->book()->process($from, $to);
        $shifts = $this->book()->shifts();
        $rows = $this->days($from, $to, $f)->where('a.status', 'MISSING_PUNCH')->get();
        $pending = DB::table('hr_attendance_corrections')->where('company_id', $this->caller()->companyId)->where('status', 'PENDING')->get()->keyBy(fn ($c) => $c->employee_id.'|'.substr((string) $c->date, 0, 10));
        $corrections = DB::table('hr_attendance_corrections as c')->join('hr_employees as e', 'e.id', '=', 'c.employee_id')->leftJoin('users as r', 'r.id', '=', 'c.requested_by')->leftJoin('users as a', 'a.id', '=', 'c.approved_by')
            ->where('c.company_id', $this->caller()->companyId)->whereBetween('c.date', [$from, $to])->orderByDesc('c.created_at')
            ->get(['c.*', 'e.employee_code', 'e.name', 'r.name as requested', 'a.name as approved']);

        return ApiResponse::ok([
            'from' => $from, 'to' => $to,
            'rows' => $rows->map(fn ($r) => $this->dayRow($r, $shifts) + ['pending' => isset($pending[$r->employee_id.'|'.substr((string) $r->date, 0, 10)])])->values()->all(),
            'corrections' => $corrections->map(fn ($c) => [
                'id' => $c->id, 'code' => $c->employee_code, 'name' => $c->name, 'date' => substr((string) $c->date, 0, 10), 'old' => trim((\App\Shared\Support\Clock::hm($c->old_in) ?? '—').' – '.(\App\Shared\Support\Clock::hm($c->old_out) ?? '—')),
                'correct_in' => $c->correct_in, 'correct_out' => $c->correct_out, 'reason' => $c->reason, 'requested_by' => $c->requested, 'approved_by' => $c->approved,
                'decided_at' => $c->decided_at ? CarbonImmutable::parse($c->decided_at)->timezone(self::TZ)->format('Y-m-d H:i') : null, 'status' => $c->status,
            ])->all(),
            'pending' => DB::table('hr_attendance_corrections')->where('company_id', $this->caller()->companyId)->where('status', 'PENDING')->count(),
        ]);
    }

    /** Correct: the right IN and OUT for a missing-punch day, with why — for approval. */
    public function requestCorrection(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.attendance.manage');
        $d = $request->validate([
            'employee' => ['required', 'string'], 'date' => ['required', 'date', 'before_or_equal:today'],
            'correct_in' => ['nullable', 'date_format:H:i', 'required_without:correct_out'], 'correct_out' => ['nullable', 'date_format:H:i'], 'reason' => ['required', 'string', 'max:500'],
        ], ['correct_in.required_without' => __('hrpayroll.att.v.one_time'), 'reason.required' => __('hrpayroll.att.v.reason'), 'date_format' => __('hrpayroll.att.v.time')]);
        $e = $this->employee($d['employee']);
        $day = DB::table('hr_attendance_days')->where('employee_id', $e->id)->whereDate('date', $d['date'])->first();
        if (DB::table('hr_attendance_corrections')->where('employee_id', $e->id)->whereDate('date', $d['date'])->where('status', 'PENDING')->exists()) {
            throw ValidationException::withMessages(['date' => __('hrpayroll.att.v.pending_exists')]);
        }
        DB::table('hr_attendance_corrections')->insert([
            'id' => strtolower((string) Str::ulid()), 'company_id' => $this->caller()->companyId, 'employee_id' => $e->id, 'date' => $d['date'],
            'old_in' => $this->hm($day?->in_at), 'old_out' => $this->hm($day?->out_at), 'correct_in' => $d['correct_in'] ?? null, 'correct_out' => $d['correct_out'] ?? null,
            'reason' => $d['reason'], 'status' => 'PENDING', 'requested_by' => $this->caller()->auditUserId(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return ApiResponse::created(['message' => __('hrpayroll.att.done.requested', ['code' => $e->employee_code, 'date' => $d['date']])]);
    }

    /** Approve or reject a correction; an approved one is written onto the day. */
    public function decideCorrection(Request $request, string $id): JsonResponse
    {
        $this->allow('hrpayroll.attendance.approve');
        $decision = $request->validate(['decision' => ['required', Rule::in(['APPROVE', 'REJECT'])]])['decision'];
        $c = DB::table('hr_attendance_corrections')->where('company_id', $this->caller()->companyId)->where('id', $id)->first() ?? abort(404);
        if ($c->status !== 'PENDING') {
            throw ValidationException::withMessages(['correction' => __('hrpayroll.att.v.decided')]);
        }
        DB::transaction(function () use ($c, $decision): void {
            DB::table('hr_attendance_corrections')->where('id', $c->id)->update(['status' => $decision === 'APPROVE' ? 'APPROVED' : 'REJECTED', 'approved_by' => $this->caller()->auditUserId(), 'decided_at' => now(), 'updated_at' => now()]);
            if ($decision === 'APPROVE') {
                $date = substr((string) $c->date, 0, 10);
                $in = $c->correct_in ?? $c->old_in;
                $out = $c->correct_out ?? $c->old_out;
                $this->writeDay($c->employee_id, $date, $this->book()->fromTimes($c->employee_id, $date, $in, $out) + ['remarks' => mb_substr($c->reason, 0, 300)]);
            }
        });

        return ApiResponse::ok(['message' => __($decision === 'APPROVE' ? 'hrpayroll.att.done.approved' : 'hrpayroll.att.done.rejected')]);
    }

    /* ── Attendance Correction (the month chart) ───────────────────── */

    public function month(Request $request): JsonResponse
    {
        $this->allowEither('hrpayroll.attendance.view', 'hrpayroll.attendance.manage');
        $d = $request->validate(['employee' => ['required', 'string'], 'year' => ['required', 'integer', 'min:2000', 'max:2100'], 'month' => ['required', 'integer', 'min:1', 'max:12']], ['employee.required' => __('hrpayroll.att.v.employee')]);
        $e = $this->employee($d['employee']);
        $first = CarbonImmutable::create((int) $d['year'], (int) $d['month'], 1, 0, 0, 0, self::TZ);
        $last = $first->endOfMonth();
        $this->book()->process($first->toDateString(), $last->toDateString(), [$e->id]);
        $shifts = $this->book()->shifts();
        $days = DB::table('hr_attendance_days')->where('employee_id', $e->id)->whereBetween('date', [$first->toDateString(), $last->toDateString()])->get()->keyBy(fn ($r) => substr((string) $r->date, 0, 10));
        $rows = [];
        for ($x = $first; $x->lessThanOrEqualTo($last); $x = $x->addDay()) {
            $r = $days[$x->toDateString()] ?? null;
            $rows[] = ['date' => $x->toDateString(), 'day' => AttendanceBook::DAYS[$x->dayOfWeek], 'future' => $x->greaterThan(CarbonImmutable::now(self::TZ)->startOfDay()), 'exists' => (bool) $r]
                + ($r ? $this->dayRow((object) ((array) $r + ['employee_code' => $e->employee_code, 'name' => $e->name, 'department' => null, 'section' => null, 'line' => null, 'designation' => null]), $shifts) : []);
        }
        $present = collect($rows)->whereIn('status', ['PRESENT'])->count();

        return ApiResponse::ok(['employee' => ['code' => $e->employee_code, 'name' => $e->name, 'card_no' => $e->card_no], 'rows' => $rows, 'totals' => [
            'present' => $present, 'absent' => collect($rows)->where('status', 'ABSENT')->count(), 'late' => collect($rows)->where('is_late', true)->count(),
            'missing' => collect($rows)->where('status', 'MISSING_PUNCH')->count(), 'leave' => collect($rows)->where('status', 'LEAVE')->count(), 'off' => collect($rows)->whereIn('status', ['HOLIDAY', 'WEEKLY_OFF'])->count(),
            'ot' => round(collect($rows)->sum(fn ($r) => (float) ($r['ot'] ?? 0)), 2),
        ]]);
    }

    /** Save a day of the chart: status, IN, OUT, late and OT as entered — a past day may be entered new. */
    public function saveDay(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.attendance.manage');
        $d = $request->validate([
            'employee' => ['required', 'string'], 'date' => ['required', 'date', 'before_or_equal:today'], 'status' => ['required', Rule::in(AttendanceBook::STATUSES)],
            'in' => ['nullable', 'date_format:H:i'], 'out' => ['nullable', 'date_format:H:i'], 'late' => ['nullable', 'integer', 'min:0', 'max:1440'], 'ot' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'remarks' => ['nullable', 'string', 'max:300'],
        ], ['date_format' => __('hrpayroll.att.v.time'), 'date.before_or_equal' => __('hrpayroll.att.v.future')]);
        $e = $this->employee($d['employee']);
        $calc = ($d['in'] ?? null) || ($d['out'] ?? null) ? $this->book()->fromTimes($e->id, $d['date'], $d['in'] ?? null, $d['out'] ?? null) : ['in_at' => null, 'out_at' => null, 'worked_minutes' => null, 'early_out_minutes' => 0, 'is_early_out' => false];
        $late = isset($d['late']) ? (int) $d['late'] : (int) ($calc['late_minutes'] ?? 0);
        // What was entered wins over what the times work out to.
        $values = array_merge($calc, [
            'status' => $d['status'], 'late_minutes' => $late, 'is_late' => $late > 0,
            'ot_minutes' => isset($d['ot']) ? (int) round((float) $d['ot'] * 60) : ($calc['ot_minutes'] ?? null), 'remarks' => $d['remarks'] ?? null,
        ]);
        $this->writeDay($e->id, $d['date'], $values);

        return ApiResponse::ok(['message' => __('hrpayroll.att.done.saved', ['code' => $e->employee_code, 'date' => $d['date']])]);
    }

    /** Delete a day: its correction goes, and the day is worked out again from the punches. */
    public function deleteDay(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.attendance.manage');
        $d = $request->validate(['employee' => ['required', 'string'], 'date' => ['required', 'date']]);
        $e = $this->employee($d['employee']);
        DB::table('hr_attendance_days')->where('employee_id', $e->id)->whereDate('date', $d['date'])->delete();
        $this->book()->process($d['date'], $d['date'], [$e->id]);

        return ApiResponse::ok(['message' => __('hrpayroll.att.done.deleted', ['code' => $e->employee_code, 'date' => $d['date']])]);
    }

    /* ── Monthly Attendance Summary ────────────────────────────────── */

    public function summary(Request $request): JsonResponse
    {
        $this->allowEither('hrpayroll.attendance.view', 'hrpayroll.attendance.manage');

        return ApiResponse::ok($this->summaryData($request));
    }

    public function summaryExport(Request $request, string $format): Response
    {
        $this->allowEither('hrpayroll.attendance.view', 'hrpayroll.attendance.manage');
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);
        $s = $this->summaryData($request);
        $days = range(1, $s['days']);
        $columns = [['label' => __('hrpayroll.att.col.code')], ['label' => __('hrpayroll.att.col.employee')], ['label' => __('hrpayroll.att.col.designation')],
            ...array_map(fn ($n) => ['label' => (string) $n], $days),
            ...array_map(fn ($k) => ['label' => __("hrpayroll.att.sum.{$k}"), 'numeric' => true], ['present', 'absent', 'leave', 'off', 'ot'])];
        $body = array_map(fn ($r) => [$r['code'], $r['name'], $r['designation'], ...array_map(fn ($n) => $r['cells'][$n - 1]['mark'].($r['cells'][$n - 1]['ot'] ? ' '.$r['cells'][$n - 1]['ot'] : ''), $days),
            $r['present'], $r['absent'], $r['leave'], $r['off'], $r['ot']], $s['rows']);
        $meta = [__('hrpayroll.att.f.month') => $s['label'], __('hrpayroll.att.f.department') => $s['department'], __('hrpayroll.att.f.section') => $s['section'] ?? __('hrpayroll.att.all_sections')];

        return $this->file($format, __('hrpayroll.emp.screens.monthly-attendance-summary'), $columns, $body, $meta, null, 'monthly-attendance-'.$s['period']);
    }

    /** @return array<string, mixed> */
    private function summaryData(Request $request): array
    {
        $c = $this->caller()->companyId;
        $d = $request->validate(['year' => ['required', 'integer', 'min:2000', 'max:2100'], 'month' => ['required', 'integer', 'min:1', 'max:12'],
            'department' => ['required', Rule::exists('departments', 'id')->where('company_id', $c)], 'section' => ['nullable', Rule::exists('sections', 'id')->where('company_id', $c)]],
            ['department.required' => __('hrpayroll.att.v.department')]);
        $first = CarbonImmutable::create((int) $d['year'], (int) $d['month'], 1, 0, 0, 0, self::TZ);
        $last = $first->endOfMonth();
        $employees = DB::table('hr_employees as e')->leftJoin('designations as g', 'g.id', '=', 'e.designation_id')->where('e.company_id', $c)->where('e.department_id', $d['department'])
            ->when($d['section'] ?? null, fn ($q, $s) => $q->where('e.section_id', $s))->orderBy('e.employee_code')->get(['e.id', 'e.employee_code', 'e.name', 'g.name as designation']);
        $this->book()->process($first->toDateString(), $last->toDateString(), $employees->pluck('id')->all());
        $days = DB::table('hr_attendance_days')->whereIn('employee_id', $employees->pluck('id'))->whereBetween('date', [$first->toDateString(), $last->toDateString()])->get()
            ->groupBy('employee_id')->map(fn ($g) => $g->keyBy(fn ($r) => (int) substr((string) $r->date, 8, 2)));
        $marks = ['PRESENT' => 'P', 'ABSENT' => 'A', 'LEAVE' => 'LV', 'MISSING_PUNCH' => 'MP', 'HOLIDAY' => 'H', 'WEEKLY_OFF' => 'W'];
        $rows = $employees->map(function ($e) use ($days, $first, $marks) {
            $cells = [];
            $t = ['present' => 0, 'absent' => 0, 'leave' => 0, 'off' => 0, 'ot' => 0.0];
            for ($n = 1; $n <= $first->daysInMonth; $n++) {
                $r = $days[$e->id][$n] ?? null;
                $mark = $r ? ($marks[$r->status] ?? '') : '';
                if ($r && $r->status === 'PRESENT' && $r->is_late) {
                    $mark = 'L';
                }
                $ot = $r && $r->ot_minutes ? round($r->ot_minutes / 60, 2) : 0;
                $cells[] = ['mark' => $mark, 'ot' => $ot ?: null];
                $t['present'] += $r && in_array($r->status, ['PRESENT', 'MISSING_PUNCH'], true) ? 1 : 0;
                $t['absent'] += $r && $r->status === 'ABSENT' ? 1 : 0;
                $t['leave'] += $r && $r->status === 'LEAVE' ? 1 : 0;
                $t['off'] += $r && in_array($r->status, ['HOLIDAY', 'WEEKLY_OFF'], true) ? 1 : 0;
                $t['ot'] += $ot;
            }

            return ['code' => $e->employee_code, 'name' => $e->name, 'designation' => $e->designation, 'cells' => $cells] + $t + ['ot' => round($t['ot'], 2)];
        })->values()->all();

        return [
            'period' => $first->format('Y-m'), 'label' => $first->format('F Y'), 'days' => $first->daysInMonth,
            'weekdays' => array_map(fn ($n) => AttendanceBook::DAYS[$first->addDays($n - 1)->dayOfWeek], range(1, $first->daysInMonth)),
            'department' => (string) DB::table('departments')->where('id', $d['department'])->value('name'), 'section' => ($d['section'] ?? null) ? (string) DB::table('sections')->where('id', $d['section'])->value('name') : null,
            'rows' => $rows,
        ];
    }

    /* ── Helpers ───────────────────────────────────────────────────── */

    private function book(): AttendanceBook
    {
        return new AttendanceBook($this->caller()->companyId);
    }

    /** @return array{0: string, 1: string, 2: array<string, mixed>} the range asked for (or named: today, yesterday, this week, this month) and the filters */
    private function range(Request $request, int $defaultBack = 0): array
    {
        $f = $request->validate([
            'range' => ['nullable', Rule::in(['today', 'yesterday', 'week', 'last7', 'month'])], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
            'department' => ['nullable', 'string'], 'section' => ['nullable', 'string'], 'line' => ['nullable', 'string'], 'employee' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in([...AttendanceBook::STATUSES, 'LATE', 'EARLY_OUT'])],
        ]);
        $today = CarbonImmutable::now(self::TZ)->startOfDay();
        [$from, $to] = match ($f['range'] ?? null) {
            'yesterday' => [$today->subDay(), $today->subDay()],
            'week' => [$today->startOfWeek(CarbonImmutable::SATURDAY), $today],
            'last7' => [$today->subDays(6), $today],
            'month' => [$today->startOfMonth(), $today],
            'today' => [$today, $today],
            default => [isset($f['from']) ? CarbonImmutable::parse($f['from'], self::TZ) : $today->subDays($defaultBack), isset($f['to']) ? CarbonImmutable::parse($f['to'], self::TZ) : $today],
        };
        if ($to->lessThan($from)) {
            throw ValidationException::withMessages(['to' => __('hrpayroll.att.v.range')]);
        }
        if ($from->diffInDays($to) + 1 > self::MAX_DAYS) {
            throw ValidationException::withMessages(['to' => __('hrpayroll.att.v.range_max', ['n' => self::MAX_DAYS])]);
        }

        return [$from->toDateString(), $to->toDateString(), $f];
    }

    /** The attendance days of a range, with the employee's place; filters by department, section, line, employee and status. */
    private function days(string $from, string $to, array $f): Builder
    {
        $status = $f['status'] ?? null;

        return DB::table('hr_attendance_days as a')->join('hr_employees as e', 'e.id', '=', 'a.employee_id')
            ->leftJoin('departments as dp', 'dp.id', '=', 'e.department_id')->leftJoin('sections as sc', 'sc.id', '=', 'e.section_id')
            ->leftJoin('production_lines as ln', 'ln.id', '=', 'e.line_id')->leftJoin('designations as dg', 'dg.id', '=', 'e.designation_id')
            ->where('a.company_id', $this->caller()->companyId)->whereBetween('a.date', [$from, $to])
            ->when($f['department'] ?? null, fn ($q, $v) => $q->where('e.department_id', $v))->when($f['section'] ?? null, fn ($q, $v) => $q->where('e.section_id', $v))
            ->when($f['line'] ?? null, fn ($q, $v) => $q->where('e.line_id', $v))->when($f['employee'] ?? null, fn ($q, $v) => $q->where('e.employee_code', $v))
            ->when($status === 'LATE', fn ($q) => $q->where('a.is_late', true))->when($status === 'EARLY_OUT', fn ($q) => $q->where('a.is_early_out', true))
            ->when($status && ! in_array($status, ['LATE', 'EARLY_OUT'], true), fn ($q) => $q->where('a.status', $status))
            ->orderBy('a.date')->orderBy('e.employee_code')
            ->select('a.*', 'e.employee_code', 'e.name', 'dp.name as department', 'sc.name as section', 'ln.name as line', 'dg.name as designation');
    }

    /** @return array<string, mixed> */
    private function dayRow(object $r, Collection $shifts): array
    {
        return [
            'id' => $r->id, 'code' => $r->employee_code, 'name' => $r->name, 'department' => $r->department, 'section' => $r->section, 'line' => $r->line, 'designation' => $r->designation,
            'date' => substr((string) $r->date, 0, 10), 'shift' => AttendanceBook::label($shifts[$r->shift_id] ?? null), 'in' => $this->hm($r->in_at), 'out' => $this->hm($r->out_at),
            'worked' => $r->worked_minutes === null ? null : round($r->worked_minutes / 60, 2), 'late' => (int) $r->late_minutes, 'early' => (int) $r->early_out_minutes,
            'ot' => $r->ot_minutes === null ? null : round($r->ot_minutes / 60, 2), 'status' => $r->status, 'is_late' => (bool) $r->is_late, 'is_early_out' => (bool) $r->is_early_out,
            'corrected' => (bool) $r->corrected, 'remarks' => $r->remarks,
        ];
    }

    private function hasStatus(array $r, string $s): bool
    {
        return match ($s) {
            'LATE' => $r['is_late'], 'EARLY_OUT' => $r['is_early_out'], default => $r['status'] === $s,
        };
    }

    private function hm(mixed $at): ?string
    {
        return $at ? CarbonImmutable::parse($at)->timezone(self::TZ)->format('H:i') : null;
    }

    private function iso(mixed $at): ?string
    {
        return $at ? CarbonImmutable::parse($at)->toIso8601String() : null;
    }

    private function employee(string $code): object
    {
        return DB::table('hr_employees')->where('company_id', $this->caller()->companyId)->where('employee_code', $code)->first()
            ?? throw ValidationException::withMessages(['employee' => __('hrpayroll.att.v.employee')]);
    }

    /** @param array<string, mixed> $values a corrected day, written over whatever was worked out */
    private function writeDay(string $employeeId, string $date, array $values): void
    {
        $now = now();
        $keep = array_intersect_key($values, array_flip(['shift_id', 'in_at', 'out_at', 'worked_minutes', 'late_minutes', 'early_out_minutes', 'ot_minutes', 'status', 'is_late', 'is_early_out', 'remarks']));
        $keep += ['corrected' => true, 'corrected_by' => $this->caller()->auditUserId(), 'processed_at' => $now, 'updated_at' => $now];
        $done = DB::table('hr_attendance_days')->where('employee_id', $employeeId)->whereDate('date', $date)->update($keep);
        if (! $done) {
            DB::table('hr_attendance_days')->insert($keep + ['id' => strtolower((string) Str::ulid()), 'company_id' => $this->caller()->companyId, 'employee_id' => $employeeId, 'date' => $date,
                'late_minutes' => $keep['late_minutes'] ?? 0, 'early_out_minutes' => $keep['early_out_minutes'] ?? 0, 'status' => $keep['status'] ?? 'PRESENT', 'created_at' => $now]);
        }
    }

    /**
     * @param  list<array{label: string, numeric?: bool}>  $columns
     * @param  list<list<mixed>>  $body
     * @param  array<string, string>  $meta
     */
    private function file(string $format, string $title, array $columns, array $body, array $meta, ?array $total, string $name): Response
    {
        if ($format === 'pdf') {
            return response((new TablePdf($title, $columns, $body, $meta, $total))->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$name.'.pdf"']);
        }

        return response()->streamDownload(function () use ($columns, $body): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues(array_map(fn ($c) => $c['label'], $columns)));
            foreach ($body as $r) {
                $writer->addRow(Row::fromValues(array_map(fn ($v) => $v ?? '', $r)));
            }
            $writer->close();
        }, $name.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
