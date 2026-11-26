<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Http\Controllers\Api;

use App\Modules\HrPayroll\Support\AttendanceBook;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * HR & Payroll → Attendance & Shift → Shift Roster (AnnotechERP 24/04), as
 * Brickland lays it out: Assign shift — Individual, Multiple Employee,
 * Line-wise or Weekly Rotation — with the weekly off days, then the current
 * roster (in force or upcoming) with its batch.
 *
 * The newest assignment covering a day decides that day's shift. Weekly Off
 * left empty keeps each employee's usual off day (their latest assignment's,
 * else Friday). A weekly rotation is one assignment a week, the pattern
 * repeating over the weeks. After an assignment, or a removal, the days it
 * covers up to today are worked out again from the punches.
 */
class HrRosterApiController extends ApiController
{
    private const MODES = ['INDIVIDUAL', 'MULTIPLE', 'LINE', 'ROTATION'];

    private const MAX_WEEKS = 26;

    public function options(): JsonResponse
    {
        $this->allowEither('hrpayroll.attendance.view', 'hrpayroll.attendance.manage');
        $c = $this->caller()->companyId;
        $book = new AttendanceBook($c);
        $current = $this->current();

        return ApiResponse::ok([
            'today' => CarbonImmutable::now('Asia/Dhaka')->toDateString(),
            'shifts' => $book->shifts()->map(fn ($s) => ['value' => $s->id, 'label' => AttendanceBook::label($s), 'code' => $s->code])->values()->all(),
            'employees' => DB::table('hr_employees as e')->leftJoin('departments as d', 'd.id', '=', 'e.department_id')->leftJoin('sections as s', 's.id', '=', 'e.section_id')->leftJoin('production_lines as l', 'l.id', '=', 'e.line_id')
                ->where('e.company_id', $c)->where('e.status', '!=', 'SEPARATED')->orderBy('e.employee_code')
                ->get(['e.id', 'e.employee_code', 'e.name', 'e.department_id', 'e.section_id', 'e.line_id', 'd.name as department', 's.name as section', 'l.name as line'])
                ->map(fn ($e) => ['value' => $e->employee_code, 'label' => "{$e->employee_code} — {$e->name}", 'name' => $e->name, 'department_id' => $e->department_id, 'section_id' => $e->section_id,
                    'line_id' => $e->line_id, 'line' => $e->line, 'current' => $current[$e->id] ?? null])->all(),
            'days' => AttendanceBook::DAYS,
        ]);
    }

    /** The current roster: assignments in force or upcoming, newest first. */
    public function index(Request $request): JsonResponse
    {
        $this->allowEither('hrpayroll.attendance.view', 'hrpayroll.attendance.manage');
        $f = $request->validate(['department' => ['nullable', 'string'], 'section' => ['nullable', 'string'], 'line' => ['nullable', 'string'], 'employee' => ['nullable', 'string'], 'shift' => ['nullable', 'string']]);
        $today = CarbonImmutable::now('Asia/Dhaka')->toDateString();
        $shifts = (new AttendanceBook($this->caller()->companyId))->shifts();
        $rows = DB::table('hr_shift_rosters as r')->join('hr_employees as e', 'e.id', '=', 'r.employee_id')->leftJoin('production_lines as l', 'l.id', '=', 'e.line_id')
            ->where('r.company_id', $this->caller()->companyId)->where(fn ($q) => $q->whereNull('r.to_date')->orWhere('r.to_date', '>=', $today))
            ->when($f['department'] ?? null, fn ($q, $v) => $q->where('e.department_id', $v))->when($f['section'] ?? null, fn ($q, $v) => $q->where('e.section_id', $v))
            ->when($f['line'] ?? null, fn ($q, $v) => $q->where('e.line_id', $v))->when($f['employee'] ?? null, fn ($q, $v) => $q->where('e.employee_code', $v))
            ->when($f['shift'] ?? null, fn ($q, $v) => $q->where('r.shift_id', $v))
            ->orderByDesc('r.created_at')->orderBy('e.employee_code')->orderBy('r.from_date')->limit(3000)
            ->get(['r.*', 'e.employee_code', 'e.name', 'l.name as line']);

        return ApiResponse::ok($rows->map(fn ($r) => [
            'id' => $r->id, 'code' => $r->employee_code, 'name' => $r->name, 'line' => $r->line, 'shift' => AttendanceBook::label($shifts[$r->shift_id] ?? null),
            'from' => substr((string) $r->from_date, 0, 10), 'to' => $r->to_date ? substr((string) $r->to_date, 0, 10) : null, 'weekly_off' => json_decode((string) $r->weekly_off, true) ?: [],
            'mode' => $r->mode, 'batch' => $r->batch, 'remarks' => $r->remarks,
        ])->values()->all());
    }

    public function assign(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.attendance.manage');
        $c = $this->caller()->companyId;
        $mode = $request->validate(['mode' => ['required', Rule::in(self::MODES)]])['mode'];
        $rotation = $mode === 'ROTATION';
        $shift = Rule::exists('hr_shifts', 'id')->where('company_id', $c);
        $d = $request->validate([
            'employee' => [$mode === 'INDIVIDUAL' ? 'required' : 'nullable', 'string'],
            'employees' => [in_array($mode, ['MULTIPLE', 'ROTATION'], true) ? 'required' : 'nullable', 'array', 'min:1', 'max:3000'], 'employees.*' => ['string'],
            'line' => [$mode === 'LINE' ? 'required' : 'nullable', Rule::exists('production_lines', 'id')->where('company_id', $c)],
            'shift' => [$rotation ? 'nullable' : 'required', $shift], 'pattern' => [$rotation ? 'required' : 'nullable', 'array', 'min:2', 'max:8'], 'pattern.*' => ['required', $shift],
            'weeks' => [$rotation ? 'required' : 'nullable', 'integer', 'min:1', 'max:'.self::MAX_WEEKS],
            'from' => ['required', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'],
            'weekly_off' => ['nullable', 'array'], 'weekly_off.*' => [Rule::in(AttendanceBook::DAYS)], 'remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'employee.required' => __('hrpayroll.att.v.employee'), 'employees.required' => __('hrpayroll.att.v.employees'), 'line.required' => __('hrpayroll.att.v.line'),
            'shift.required' => __('hrpayroll.att.v.shift'), 'pattern.required' => __('hrpayroll.att.v.pattern'), 'pattern.min' => __('hrpayroll.att.v.pattern'), 'pattern.*.required' => __('hrpayroll.att.v.pattern'),
            'from.required' => __('hrpayroll.att.v.from'), 'to.after_or_equal' => __('hrpayroll.att.v.range'),
        ]);
        $people = match ($mode) {
            'INDIVIDUAL' => $this->people([$d['employee']]),
            'LINE' => DB::table('hr_employees')->where('company_id', $c)->where('line_id', $d['line'])->where('status', '!=', 'SEPARATED')->get(),
            default => $this->people($d['employees']),
        };
        if ($people->isEmpty()) {
            throw ValidationException::withMessages(['line' => __('hrpayroll.att.v.line_empty')]);
        }
        $batch = $this->nextBatch();
        $from = CarbonImmutable::parse($d['from'], 'Asia/Dhaka');
        $now = now();
        $rows = [];
        foreach ($people as $e) {
            $off = ! empty($d['weekly_off']) ? array_values(array_unique($d['weekly_off'])) : $this->usualOff($e->id);
            $base = ['company_id' => $c, 'employee_id' => $e->id, 'weekly_off' => json_encode($off), 'mode' => $mode, 'batch' => $batch, 'remarks' => $d['remarks'] ?? null, 'created_by' => $this->caller()->auditUserId()];
            if ($rotation) {
                for ($w = 0; $w < (int) $d['weeks']; $w++) {
                    $start = $from->addWeeks($w);
                    // One second apart, so the newest-first order keeps the weeks in order.
                    $at = $now->copy()->addSeconds($w);
                    $rows[] = $base + ['id' => strtolower((string) Str::ulid()), 'shift_id' => $d['pattern'][$w % count($d['pattern'])], 'from_date' => $start->toDateString(), 'to_date' => $start->addDays(6)->toDateString(), 'created_at' => $at, 'updated_at' => $at];
                }
            } else {
                $rows[] = $base + ['id' => strtolower((string) Str::ulid()), 'shift_id' => $d['shift'], 'from_date' => $d['from'], 'to_date' => $d['to'] ?? null, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        DB::transaction(fn () => collect($rows)->chunk(500)->each(fn ($chunk) => DB::table('hr_shift_rosters')->insert($chunk->all())));
        $until = $rotation ? $from->addWeeks((int) $d['weeks'])->subDay()->toDateString() : ($d['to'] ?? CarbonImmutable::now('Asia/Dhaka')->toDateString());
        (new AttendanceBook($c))->process($d['from'], $until, $people->pluck('id')->all());

        return ApiResponse::created(['batch' => $batch, 'message' => __('hrpayroll.att.done.assigned', ['n' => $people->count(), 'batch' => $batch])]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->allow('hrpayroll.attendance.manage');
        $r = DB::table('hr_shift_rosters')->where('company_id', $this->caller()->companyId)->where('id', $id)->first() ?? abort(404);
        DB::table('hr_shift_rosters')->where('id', $r->id)->delete();
        (new AttendanceBook($this->caller()->companyId))->process(substr((string) $r->from_date, 0, 10), $r->to_date ? substr((string) $r->to_date, 0, 10) : CarbonImmutable::now('Asia/Dhaka')->toDateString(), [$r->employee_id]);

        return ApiResponse::ok(['message' => __('hrpayroll.att.done.unassigned')]);
    }

    /* ── Helpers ───────────────────────────────────────────────────── */

    /** @return array<string, string> each employee's shift today */
    private function current(): array
    {
        $today = CarbonImmutable::now('Asia/Dhaka')->toDateString();
        $shifts = (new AttendanceBook($this->caller()->companyId))->shifts();
        $out = [];
        foreach (DB::table('hr_shift_rosters')->where('company_id', $this->caller()->companyId)->where('from_date', '<=', $today)->where(fn ($q) => $q->whereNull('to_date')->orWhere('to_date', '>=', $today))
            ->orderByDesc('created_at')->orderByDesc('id')->get(['employee_id', 'shift_id']) as $r) {
            $out[$r->employee_id] ??= AttendanceBook::label($shifts[$r->shift_id] ?? null);
        }

        return $out;
    }

    /** @return list<string> the employee's usual weekly off: their latest assignment's, else Friday */
    private function usualOff(string $employeeId): array
    {
        $last = DB::table('hr_shift_rosters')->where('employee_id', $employeeId)->orderByDesc('created_at')->orderByDesc('id')->value('weekly_off');

        return ($last ? json_decode((string) $last, true) : null) ?: ['FRI'];
    }

    private function nextBatch(): string
    {
        $n = (int) DB::table('hr_shift_rosters')->where('company_id', $this->caller()->companyId)->where('batch', 'like', 'RS-%')->selectRaw('max(cast(substring(batch from 4) as integer)) as n')->value('n');

        return 'RS-'.str_pad((string) ($n + 1), 6, '0', STR_PAD_LEFT);
    }

    /** @param list<string> $codes */
    private function people(array $codes): Collection
    {
        $people = DB::table('hr_employees')->where('company_id', $this->caller()->companyId)->whereIn('employee_code', $codes)->get();
        if ($people->count() !== count(array_unique($codes))) {
            throw ValidationException::withMessages(['employees' => __('hrpayroll.att.v.employees')]);
        }

        return $people;
    }
}
