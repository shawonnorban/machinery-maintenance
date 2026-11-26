<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Http\Controllers\Api;

use App\Modules\HrPayroll\Support\SalaryStructures;
use App\Modules\HrPayroll\Support\SeparationBook;
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
 * HR & Payroll → Separation (AnnotechERP 24/08), as Brickland lays the
 * three screens out:
 *
 * - **Resignation / Release:** a resignation, release or termination sent
 *   for approval (application and last working dates, notice days, reason);
 *   approve or reject with a note, withdraw while pending, reopen a rejected
 *   one; an approved one takes the employee off the roster after the last
 *   working day and can start the final settlement.
 * - **Lefty Management:** report an employee who left without notice (last
 *   attendance checked), investigate, confirm (off the roster from the last
 *   working date) or reject; reinstate or close a confirmed one. Each move
 *   is kept in the case history.
 * - **Final Settlement:** records and a new-settlement form — dues worked
 *   out from the records, edited while a draft, recalculated, approved
 *   (locked), paid and printed.
 */
class HrSeparationApiController extends ApiController
{
    private const TYPES = ['RESIGNATION', 'RELEASE', 'TERMINATION'];

    private const SETTLEMENT_TYPES = ['RESIGN_FROM_JOB', 'DISABLE_FOR_LONG_ABSENTEEISM', 'TERMINATION', 'RETIREMENT', 'DEATH'];

    private const FOR_TYPE = ['RESIGNATION' => 'RESIGN_FROM_JOB', 'RELEASE' => 'RESIGN_FROM_JOB', 'TERMINATION' => 'TERMINATION'];

    /** Lefty moves: action => [from statuses, to status, permission, note required]. */
    private const LEFTY = [
        'INVESTIGATE' => [['PENDING_REVIEW'], 'UNDER_INVESTIGATION', 'hrpayroll.separation.approve', false],
        'CONFIRM' => [['PENDING_REVIEW', 'UNDER_INVESTIGATION'], 'CONFIRMED', 'hrpayroll.separation.approve', false],
        'REJECT' => [['PENDING_REVIEW', 'UNDER_INVESTIGATION'], 'REJECTED', 'hrpayroll.separation.approve', true],
        'REINSTATE' => [['CONFIRMED'], 'REINSTATED', 'hrpayroll.separation.approve', true],
        'CLOSE' => [['CONFIRMED', 'REJECTED', 'REINSTATED'], 'CLOSED', 'hrpayroll.separation.approve', false],
    ];

    private const MONEY = ['salary_due', 'ot_due', 'bonus_due', 'leave_encashment', 'deduction', 'loan_recovery', 'other_adjustment'];

    /* ── Resignation / Release ─────────────────────────────────────── */

    public function separations(): JsonResponse
    {
        $this->allowEither('hrpayroll.separation.view', 'hrpayroll.separation.manage', 'hrpayroll.separation.approve');
        $this->book()->applyDue();

        return ApiResponse::ok(['rows' => $this->separationRows(), 'can_request' => $this->caller()->can('hrpayroll.separation.manage'), 'can_approve' => $this->caller()->can('hrpayroll.separation.approve'), 'today' => SeparationBook::today()]);
    }

    /** Active employees by code, name, card or NID. */
    public function search(Request $request): JsonResponse
    {
        $this->allowEither('hrpayroll.separation.manage', 'hrpayroll.separation.view');
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return ApiResponse::ok([]);
        }
        $rows = $this->people()->whereIn('e.status', ['ACTIVE', 'SUSPENDED'])->where($this->matching($q))->limit(20)->get();

        return ApiResponse::ok($rows->map(fn ($e) => $this->card($e))->values()->all());
    }

    public function storeSeparation(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.separation.manage');
        $d = $request->validate([
            'employee' => ['required', 'string'], 'type' => ['required', Rule::in(self::TYPES)], 'application_date' => ['required', 'date_format:Y-m-d'],
            'last_working_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:application_date'], 'notice_days' => ['nullable', 'integer', 'min:0', 'max:365'], 'reason' => ['required', 'string', 'max:1000'],
        ], ['employee.required' => __('hrpayroll.st.v.employee'), 'application_date.required' => __('hrpayroll.st.v.application_date'), 'last_working_date.required' => __('hrpayroll.st.v.last_day'),
            'last_working_date.after_or_equal' => __('hrpayroll.st.v.last_before_application'), 'reason.required' => __('hrpayroll.st.v.reason')]);
        $e = $this->activeEmployee($d['employee']);
        $open = DB::table('hr_separations')->where('employee_id', $e->id)->where(fn ($q) => $q->where('status', 'PENDING')->orWhere(fn ($w) => $w->where('status', 'APPROVED')->whereNull('applied_at')))->value('ref_no');
        if ($open) {
            throw ValidationException::withMessages(['employee' => __('hrpayroll.st.v.open_separation', ['ref' => $open])]);
        }
        $notice = $d['notice_days'] ?? (int) CarbonImmutable::parse($d['application_date'])->diffInDays(CarbonImmutable::parse($d['last_working_date']));
        $ref = $this->nextRef('hr_separations', 'SEP-'.substr($d['application_date'], 0, 4), 5);
        DB::table('hr_separations')->insert([
            'id' => $this->ulid(), 'company_id' => $this->caller()->companyId, 'ref_no' => $ref, 'employee_id' => $e->id, 'type' => $d['type'], 'application_date' => $d['application_date'],
            'last_working_date' => $d['last_working_date'], 'notice_days' => $notice, 'reason' => trim($d['reason']), 'status' => 'PENDING', 'requested_by' => $this->caller()->auditUserId(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ApiResponse::created(['message' => __('hrpayroll.st.done.sent', ['ref' => $ref]), 'rows' => $this->separationRows()]);
    }

    public function decideSeparation(Request $request, string $ref): JsonResponse
    {
        $this->allow('hrpayroll.separation.approve');
        $row = $this->separation($ref);
        $d = $request->validate(['decision' => ['required', Rule::in(['APPROVE', 'REJECT'])], 'note' => ['nullable', 'string', 'max:1000']]);
        if ($row->status !== 'PENDING') {
            throw ValidationException::withMessages(['decision' => __('hrpayroll.st.v.decided')]);
        }
        $status = $d['decision'] === 'APPROVE' ? 'APPROVED' : 'REJECTED';
        DB::table('hr_separations')->where('id', $row->id)->update(['status' => $status, 'decision_note' => trim((string) ($d['note'] ?? '')) ?: null, 'decided_by' => $this->caller()->auditUserId(), 'decided_at' => now(), 'updated_at' => now()]);
        $this->book()->applyDue();
        $key = $status === 'REJECTED' ? 'rejected' : (substr((string) $row->last_working_date, 0, 10) < SeparationBook::today() ? 'off_roster' : 'leaves_after');

        return ApiResponse::ok(['message' => __("hrpayroll.st.done.{$key}", ['ref' => $ref, 'date' => SeparationBook::dmy(substr((string) $row->last_working_date, 0, 10))]), 'rows' => $this->separationRows()]);
    }

    public function withdrawSeparation(string $ref): JsonResponse
    {
        $this->allow('hrpayroll.separation.manage');
        $row = $this->separation($ref);
        if ($row->status !== 'PENDING') {
            throw ValidationException::withMessages(['decision' => __('hrpayroll.st.v.decided')]);
        }
        DB::table('hr_separations')->where('id', $row->id)->delete();

        return ApiResponse::ok(['message' => __('hrpayroll.st.done.withdrawn', ['ref' => $ref]), 'rows' => $this->separationRows()]);
    }

    public function reopenSeparation(string $ref): JsonResponse
    {
        $this->allow('hrpayroll.separation.approve');
        $row = $this->separation($ref);
        if ($row->status !== 'REJECTED') {
            throw ValidationException::withMessages(['decision' => __('hrpayroll.st.v.not_rejected')]);
        }
        DB::table('hr_separations')->where('id', $row->id)->update(['status' => 'PENDING', 'decided_by' => null, 'decided_at' => null, 'decision_note' => null, 'updated_at' => now()]);

        return ApiResponse::ok(['message' => __('hrpayroll.st.done.reopened', ['ref' => $ref]), 'rows' => $this->separationRows()]);
    }

    /** Create the final settlement of an approved separation, worked out from the records. */
    public function settleSeparation(string $ref): JsonResponse
    {
        $this->allow('hrpayroll.separation.manage');
        $row = $this->separation($ref);
        if ($row->status !== 'APPROVED') {
            throw ValidationException::withMessages(['decision' => __('hrpayroll.st.v.not_approved')]);
        }
        if (DB::table('hr_settlements')->where('separation_id', $row->id)->exists()) {
            throw ValidationException::withMessages(['decision' => __('hrpayroll.st.v.settled')]);
        }
        $e = DB::table('hr_employees')->where('id', $row->employee_id)->first();
        $date = substr((string) $row->last_working_date, 0, 10);
        $sref = $this->createSettlement($e, self::FOR_TYPE[$row->type], $date, $this->book()->calculate($e, $date), null, $row->id, null);

        return ApiResponse::created(['message' => __('hrpayroll.st.done.settlement_created', ['ref' => $sref]), 'rows' => $this->separationRows()]);
    }

    /* ── Lefty Management ──────────────────────────────────────────── */

    public function lefty(): JsonResponse
    {
        $this->allowEither('hrpayroll.separation.view', 'hrpayroll.separation.manage', 'hrpayroll.separation.approve');

        return ApiResponse::ok(['today' => SeparationBook::today(), 'rows' => $this->leftyRows(), 'can' => $this->leftyCan()]);
    }

    /** Active employees by code or card, with their last attendance and any open case. */
    public function leftyCandidates(Request $request): JsonResponse
    {
        $this->allowEither('hrpayroll.separation.manage', 'hrpayroll.separation.view');
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return ApiResponse::ok([]);
        }
        $rows = $this->people()->whereIn('e.status', ['ACTIVE', 'SUSPENDED'])->where($this->matching($q))->limit(20)->get();
        $today = CarbonImmutable::parse(SeparationBook::today());

        return ApiResponse::ok($rows->map(function ($e) use ($today) {
            $last = DB::table('hr_attendance_days')->where('employee_id', $e->id)->whereIn('status', ['PRESENT', 'MISSING_PUNCH'])->max('date');
            $last = $last ? substr((string) $last, 0, 10) : null;

            return $this->card($e) + [
                'last_attendance_date' => $last, 'absent_days' => $last ? (int) CarbonImmutable::parse($last)->diffInDays($today) : null,
                'open_record' => DB::table('hr_lefty_cases')->where('employee_id', $e->id)->whereIn('status', ['PENDING_REVIEW', 'UNDER_INVESTIGATION', 'CONFIRMED'])->value('ref_no'),
            ];
        })->values()->all());
    }

    public function reportLefty(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.separation.manage');
        $d = $request->validate([
            'employee' => ['required', 'string'], 'last_working_date' => ['required', 'date_format:Y-m-d'], 'reported_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:last_working_date'],
            'reason' => ['required', 'string', 'max:1000'], 'document_ref' => ['nullable', 'string', 'max:300'],
        ], ['employee.required' => __('hrpayroll.st.v.employee'), 'last_working_date.required' => __('hrpayroll.st.v.last_day'), 'reported_date.required' => __('hrpayroll.st.v.reported_date'),
            'reported_date.after_or_equal' => __('hrpayroll.st.v.reported_before'), 'reason.required' => __('hrpayroll.st.v.reason')]);
        $e = $this->activeEmployee($d['employee']);
        $open = DB::table('hr_lefty_cases')->where('employee_id', $e->id)->whereIn('status', ['PENDING_REVIEW', 'UNDER_INVESTIGATION', 'CONFIRMED'])->value('ref_no');
        if ($open) {
            throw ValidationException::withMessages(['employee' => __('hrpayroll.st.v.open_lefty', ['ref' => $open])]);
        }
        $last = DB::table('hr_attendance_days')->where('employee_id', $e->id)->whereIn('status', ['PRESENT', 'MISSING_PUNCH'])->max('date');
        $ref = $this->nextRef('hr_lefty_cases', 'LF-'.substr($d['reported_date'], 0, 4), 4);
        DB::table('hr_lefty_cases')->insert([
            'id' => $this->ulid(), 'company_id' => $this->caller()->companyId, 'ref_no' => $ref, 'employee_id' => $e->id, 'last_attendance_date' => $last ? substr((string) $last, 0, 10) : null,
            'last_working_date' => $d['last_working_date'], 'reported_date' => $d['reported_date'], 'reason' => trim($d['reason']), 'document_ref' => trim((string) ($d['document_ref'] ?? '')) ?: null,
            'status' => 'PENDING_REVIEW', 'reported_by' => $this->caller()->auditUserId(), 'history' => json_encode([$this->step('', 'PENDING_REVIEW', trim($d['reason']))], JSON_UNESCAPED_UNICODE),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ApiResponse::created(['message' => __('hrpayroll.st.done.lefty_reported', ['ref' => $ref]), 'rows' => $this->leftyRows()]);
    }

    public function moveLefty(Request $request, string $ref): JsonResponse
    {
        $d = $request->validate(['action' => ['required', Rule::in(array_keys(self::LEFTY))], 'note' => ['nullable', 'string', 'max:1000']]);
        [$from, $to, $permission, $needsNote] = self::LEFTY[$d['action']];
        $this->allow($permission);
        $row = DB::table('hr_lefty_cases')->where('company_id', $this->caller()->companyId)->where('ref_no', $ref)->first();
        abort_unless($row, 404);
        if (! in_array($row->status, $from, true)) {
            throw ValidationException::withMessages(['action' => __('hrpayroll.st.v.lefty_step')]);
        }
        $note = trim((string) ($d['note'] ?? ''));
        if ($needsNote && $note === '') {
            throw ValidationException::withMessages(['note' => __('hrpayroll.st.v.note')]);
        }
        $by = $this->caller()->auditUserId();
        DB::transaction(function () use ($row, $d, $to, $note, $by): void {
            $history = json_decode((string) $row->history, true) ?: [];
            $history[] = $this->step($row->status, $to, $note);
            $values = ['status' => $to, 'history' => json_encode($history, JSON_UNESCAPED_UNICODE), 'updated_at' => now()] + ($note !== '' ? ['remarks' => $note] : []);
            if ($d['action'] === 'INVESTIGATE') {
                $values['reviewed_by'] = $by;
            } elseif ($d['action'] === 'CONFIRM') {
                $values += ['confirmed_by' => $by, 'confirmed_at' => now()];
                $this->book()->leave($row->employee_id, substr((string) $row->last_working_date, 0, 10));
            } elseif ($d['action'] === 'REINSTATE') {
                DB::table('hr_employees')->where('id', $row->employee_id)->update(['status' => 'ACTIVE', 'active' => true, 'separation_date' => null, 'updated_at' => now()]);
            }
            DB::table('hr_lefty_cases')->where('id', $row->id)->update($values);
        });

        return ApiResponse::ok(['message' => __('hrpayroll.st.done.lefty_'.strtolower($d['action']), ['ref' => $ref]), 'rows' => $this->leftyRows()]);
    }

    /* ── Final Settlement ──────────────────────────────────────────── */

    public function settlements(): JsonResponse
    {
        $this->allowEither('hrpayroll.separation.view', 'hrpayroll.separation.manage', 'hrpayroll.separation.approve');
        $this->book()->applyDue();

        return ApiResponse::ok([
            'rows' => $this->settlementRows(), 'employees' => $this->settleable(), 'today' => SeparationBook::today(),
            'can_manage' => $this->caller()->can('hrpayroll.separation.manage'), 'can_approve' => $this->caller()->can('hrpayroll.separation.approve'),
        ]);
    }

    /** The figures worked out for an employee and date, for the form. */
    public function preview(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.separation.manage');
        $d = $request->validate(['employee' => ['required', 'string'], 'date' => ['nullable', 'date_format:Y-m-d']]);
        $e = $this->employee($d['employee']);
        [$type, $date, $info] = $this->defaults($e);
        $date = $d['date'] ?? $date;
        $v = $this->book()->calculate($e, $date);

        return ApiResponse::ok($v + ['separation' => $info, 'settlement_type' => $type, 'action_date' => $date, 'payable' => SeparationBook::payable($v)]);
    }

    public function storeSettlement(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.separation.manage');
        $d = $this->validSettlement($request);
        $e = $this->employee($d['employee']);
        if (DB::table('hr_settlements')->where('employee_id', $e->id)->where('status', 'DRAFT')->exists()) {
            throw ValidationException::withMessages(['employee' => __('hrpayroll.st.v.draft_exists')]);
        }
        [$sep, $lefty] = $this->sources($e);
        $calc = $this->book()->calculate($e, $d['action_date']);
        $values = array_map(fn ($k) => (float) ($d[$k] ?? 0), array_combine(self::MONEY, self::MONEY));
        $ref = $this->createSettlement($e, $d['settlement_type'], $d['action_date'], $values + ['notes' => $calc['notes']], $d['remarks'] ?? null, $sep, $lefty);

        return ApiResponse::created(['message' => __('hrpayroll.st.done.settlement_saved', ['ref' => $ref]), 'rows' => $this->settlementRows(), 'employees' => $this->settleable()]);
    }

    public function updateSettlement(Request $request, string $ref): JsonResponse
    {
        $this->allow('hrpayroll.separation.manage');
        $row = $this->draftSettlement($ref);
        $d = $this->validSettlement($request);
        $values = array_map(fn ($k) => round((float) ($d[$k] ?? 0), 2), array_combine(self::MONEY, self::MONEY));
        DB::table('hr_settlements')->where('id', $row->id)->update($values + [
            'settlement_type' => $d['settlement_type'], 'action_date' => $d['action_date'], 'payable' => SeparationBook::payable($values), 'remarks' => trim((string) ($d['remarks'] ?? '')) ?: null, 'updated_at' => now(),
        ]);

        return ApiResponse::ok(['message' => __('hrpayroll.st.done.settlement_saved', ['ref' => $ref]), 'rows' => $this->settlementRows(), 'employees' => $this->settleable()]);
    }

    public function destroySettlement(string $ref): JsonResponse
    {
        $this->allow('hrpayroll.separation.manage');
        $row = $this->draftSettlement($ref);
        DB::table('hr_settlements')->where('id', $row->id)->delete();

        return ApiResponse::ok(['message' => __('hrpayroll.st.done.settlement_deleted', ['ref' => $ref]), 'rows' => $this->settlementRows(), 'employees' => $this->settleable()]);
    }

    /** Calculate, Approve or Mark Paid from the row. */
    public function moveSettlement(Request $request, string $ref): JsonResponse
    {
        $d = $request->validate(['action' => ['required', Rule::in(['calculate', 'approve', 'paid'])]]);
        $this->allow($d['action'] === 'calculate' ? 'hrpayroll.separation.manage' : 'hrpayroll.separation.approve');
        $row = DB::table('hr_settlements')->where('company_id', $this->caller()->companyId)->where('ref_no', $ref)->first();
        abort_unless($row, 404);
        $need = ['calculate' => 'DRAFT', 'approve' => 'DRAFT', 'paid' => 'APPROVED'][$d['action']];
        if ($row->status !== $need) {
            throw ValidationException::withMessages(['action' => __('hrpayroll.st.v.settlement_step')]);
        }
        $by = $this->caller()->auditUserId();
        DB::transaction(function () use ($row, $d, $by): void {
            if ($d['action'] === 'calculate') {
                $e = DB::table('hr_employees')->where('id', $row->employee_id)->first();
                $v = $this->book()->calculate($e, substr((string) $row->action_date, 0, 10));
                DB::table('hr_settlements')->where('id', $row->id)->update($v + ['payable' => SeparationBook::payable($v), 'updated_at' => now()]);
            } elseif ($d['action'] === 'approve') {
                DB::table('hr_settlements')->where('id', $row->id)->update(['status' => 'APPROVED', 'approved_by' => $by, 'approved_at' => now(), 'updated_at' => now()]);
            } else {
                DB::table('hr_settlements')->where('id', $row->id)->update(['status' => 'PAID', 'paid_by' => $by, 'paid_at' => now(), 'updated_at' => now()]);
                // Approved payroll adjustments are paid with the settlement, not a later salary.
                if (abs((float) $row->other_adjustment) >= 0.01) {
                    DB::table('hr_payroll_adjustments')->where('employee_id', $row->employee_id)->where('status', 'APPROVED')->whereNull('paid_in_period_id')->update(['status' => 'APPLIED', 'updated_at' => now()]);
                }
            }
        });

        return ApiResponse::ok(['message' => __("hrpayroll.st.done.settlement_{$d['action']}", ['ref' => $ref]), 'rows' => $this->settlementRows()]);
    }

    /* ── Helpers ───────────────────────────────────────────────────── */

    private function book(): SeparationBook
    {
        return new SeparationBook($this->caller()->companyId);
    }

    private function ulid(): string
    {
        return strtolower((string) Str::ulid());
    }

    private function people()
    {
        return DB::table('hr_employees as e')->leftJoin('departments as dp', 'dp.id', '=', 'e.department_id')->leftJoin('sections as sc', 'sc.id', '=', 'e.section_id')
            ->leftJoin('production_lines as ln', 'ln.id', '=', 'e.line_id')->leftJoin('designations as d', 'd.id', '=', 'e.designation_id')->leftJoin('grades as g', 'g.id', '=', 'e.grade_id')
            ->where('e.company_id', $this->caller()->companyId)->orderBy('e.employee_code')
            ->select(['e.*', 'dp.name as department', 'sc.name as section', 'ln.name as line', 'd.name as designation', 'g.name as grade_name', 'g.code as grade_code']);
    }

    private function matching(string $q): \Closure
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], Str::lower($q)).'%';

        return fn ($w) => $w->whereRaw('lower(e.employee_code) like ?', [$like])->orWhereRaw('lower(e.name) like ?', [$like])->orWhereRaw('lower(e.card_no) like ?', [$like])->orWhereRaw('lower(coalesce(e.nid, \'\')) like ?', [$like]);
    }

    /** @return array<string, mixed> */
    private function card(object $e): array
    {
        $structure = $e->gazette_id ? SalaryStructures::find($e->gazette_id, $e->employment_type_id) : null;

        return [
            'code' => $e->employee_code, 'name' => $e->name, 'card_no' => $e->card_no, 'designation' => $e->designation, 'grade' => $e->grade_name ?: $e->grade_code,
            'department' => $e->department, 'section' => $e->section, 'line' => $e->line, 'company' => DB::table('companies')->where('id', $e->company_id)->value('name'),
            'joining_date' => $e->joining_date ? substr((string) $e->joining_date, 0, 10) : null, 'gross_a' => (float) $e->gross_a, 'structure' => $structure['label'] ?? null,
        ];
    }

    private function activeEmployee(string $code): object
    {
        $e = $this->employee($code);
        if (! in_array($e->status, ['ACTIVE', 'SUSPENDED'], true)) {
            throw ValidationException::withMessages(['employee' => __('hrpayroll.st.v.not_active')]);
        }

        return $e;
    }

    private function employee(string $code): object
    {
        $e = DB::table('hr_employees')->where('company_id', $this->caller()->companyId)->where('employee_code', $code)->first();
        if (! $e) {
            throw ValidationException::withMessages(['employee' => __('hrpayroll.st.v.employee')]);
        }

        return $e;
    }

    private function separation(string $ref): object
    {
        $row = DB::table('hr_separations')->where('company_id', $this->caller()->companyId)->where('ref_no', $ref)->first();
        abort_unless($row, 404);

        return $row;
    }

    private function draftSettlement(string $ref): object
    {
        $row = DB::table('hr_settlements')->where('company_id', $this->caller()->companyId)->where('ref_no', $ref)->first();
        abort_unless($row, 404);
        if ($row->status !== 'DRAFT') {
            throw ValidationException::withMessages(['status' => __('hrpayroll.st.v.locked')]);
        }

        return $row;
    }

    private function nextRef(string $table, string $prefix, int $width): string
    {
        $last = DB::table($table)->where('company_id', $this->caller()->companyId)->where('ref_no', 'like', "{$prefix}-%")->orderByDesc('ref_no')->value('ref_no');

        return $prefix.'-'.str_pad((string) ((int) substr((string) $last, strlen($prefix) + 1) + 1), $width, '0', STR_PAD_LEFT);
    }

    /** @return list<array<string, mixed>> */
    private function separationRows(): array
    {
        return DB::table('hr_separations as s')->join('hr_employees as e', 'e.id', '=', 's.employee_id')->leftJoin('designations as d', 'd.id', '=', 'e.designation_id')->leftJoin('sections as sc', 'sc.id', '=', 'e.section_id')
            ->leftJoin('users as u', 'u.id', '=', 's.decided_by')->leftJoin('hr_settlements as f', 'f.separation_id', '=', 's.id')
            ->where('s.company_id', $this->caller()->companyId)->orderByDesc('s.created_at')->orderByDesc('s.ref_no')
            ->get(['s.*', 'e.employee_code', 'e.name', 'd.name as designation', 'sc.name as section', 'u.name as decider', 'f.ref_no as settlement_ref', 'f.status as settlement_status'])
            ->map(fn ($s) => [
                'ref' => $s->ref_no, 'code' => $s->employee_code, 'name' => $s->name, 'designation' => $s->designation, 'section' => $s->section, 'type' => $s->type,
                'application_date' => substr((string) $s->application_date, 0, 10), 'last_working_date' => substr((string) $s->last_working_date, 0, 10), 'notice_days' => $s->notice_days === null ? null : (int) $s->notice_days,
                'reason' => $s->reason, 'status' => $s->status, 'decided_by' => $s->decider, 'decided_at' => $s->decided_at ? (string) $s->decided_at : null, 'decision_note' => $s->decision_note,
                'applied' => $s->applied_at !== null, 'settlement_ref' => $s->settlement_ref, 'settlement_status' => $s->settlement_status,
            ])->all();
    }

    /** @return array<string, mixed> */
    private function step(string $from, string $to, string $note): array
    {
        return ['at' => now()->toIso8601String(), 'by' => $this->caller()->user?->name ?? $this->caller()->label(), 'from' => $from, 'to' => $to, 'note' => $note];
    }

    /** @return array{report: bool, confirm: bool, reverse: bool} */
    private function leftyCan(): array
    {
        $approve = $this->caller()->can('hrpayroll.separation.approve');

        return ['report' => $this->caller()->can('hrpayroll.separation.manage'), 'confirm' => $approve, 'reverse' => $approve];
    }

    /** @return list<array<string, mixed>> */
    private function leftyRows(): array
    {
        $can = $this->leftyCan();

        return DB::table('hr_lefty_cases as l')->join('hr_employees as e', 'e.id', '=', 'l.employee_id')->join('companies as co', 'co.id', '=', 'l.company_id')
            ->leftJoin('departments as dp', 'dp.id', '=', 'e.department_id')->leftJoin('sections as sc', 'sc.id', '=', 'e.section_id')->leftJoin('designations as d', 'd.id', '=', 'e.designation_id')
            ->leftJoin('grades as g', 'g.id', '=', 'e.grade_id')->leftJoin('users as rb', 'rb.id', '=', 'l.reported_by')->leftJoin('users as vb', 'vb.id', '=', 'l.reviewed_by')->leftJoin('users as cb', 'cb.id', '=', 'l.confirmed_by')
            ->leftJoin('hr_settlements as f', 'f.lefty_id', '=', 'l.id')
            ->where('l.company_id', $this->caller()->companyId)->orderByDesc('l.created_at')->orderByDesc('l.ref_no')
            ->get(['l.*', 'e.employee_code', 'e.name', 'e.card_no', 'e.status as emp_status', 'co.name as company', 'dp.name as department', 'sc.name as section', 'd.name as designation', 'g.name as grade',
                'rb.name as reporter', 'vb.name as reviewer', 'cb.name as confirmer', 'f.status as settlement_status'])
            ->map(function ($l) use ($can) {
                $actions = array_values(array_filter(array_keys(self::LEFTY), fn ($a) => in_array($l->status, self::LEFTY[$a][0], true) && ($a === 'REINSTATE' ? $can['reverse'] : $can['confirm'])));

                return [
                    'ref' => $l->ref_no, 'code' => $l->employee_code, 'name' => $l->name, 'card_no' => $l->card_no, 'company' => $l->company, 'department' => $l->department, 'section' => $l->section,
                    'designation' => $l->designation, 'grade' => $l->grade, 'last_attendance_date' => $l->last_attendance_date ? substr((string) $l->last_attendance_date, 0, 10) : null,
                    'last_working_date' => substr((string) $l->last_working_date, 0, 10), 'reported_date' => substr((string) $l->reported_date, 0, 10), 'reason' => $l->reason, 'status' => $l->status,
                    'reported_by' => $l->reporter, 'reviewed_by' => $l->reviewer, 'confirmed_by' => $l->confirmer, 'confirmed_at' => $l->confirmed_at ? (string) $l->confirmed_at : null,
                    'remarks' => $l->remarks, 'document_ref' => $l->document_ref, 'payroll_eligible' => in_array($l->emp_status, ['ACTIVE', 'SUSPENDED'], true),
                    'settlement_status' => $l->settlement_status, 'history' => json_decode((string) $l->history, true) ?: [], 'actions' => $actions,
                ];
            })->all();
    }

    /** The approved separation and confirmed lefty case of an employee not yet settled. @return array{0: ?string, 1: ?string} */
    private function sources(object $e): array
    {
        $sep = DB::table('hr_separations as s')->leftJoin('hr_settlements as f', 'f.separation_id', '=', 's.id')->where('s.employee_id', $e->id)->where('s.status', 'APPROVED')->whereNull('f.id')->orderByDesc('s.last_working_date')->value('s.id');
        $lefty = DB::table('hr_lefty_cases as l')->leftJoin('hr_settlements as f', 'f.lefty_id', '=', 'l.id')->where('l.employee_id', $e->id)->whereIn('l.status', ['CONFIRMED', 'CLOSED'])->whereNotNull('l.confirmed_at')->whereNull('f.id')->orderByDesc('l.last_working_date')->value('l.id');

        return [$sep, $sep ? null : $lefty];
    }

    /** What the form starts from for an employee: type, date and the separation line. @return array{0: string, 1: string, 2: string} */
    private function defaults(object $e): array
    {
        [$sepId, $leftyId] = $this->sources($e);
        if ($sepId) {
            $s = DB::table('hr_separations')->where('id', $sepId)->first();

            return [self::FOR_TYPE[$s->type], substr((string) $s->last_working_date, 0, 10), __('hrpayroll.st.sep_line', ['ref' => $s->ref_no, 'type' => __('hrpayroll.st.type_word.'.$s->type), 'date' => SeparationBook::dmy(substr((string) $s->last_working_date, 0, 10))])];
        }
        if ($leftyId) {
            $l = DB::table('hr_lefty_cases')->where('id', $leftyId)->first();

            return ['DISABLE_FOR_LONG_ABSENTEEISM', substr((string) $l->last_working_date, 0, 10), __('hrpayroll.st.lefty_line', ['ref' => $l->ref_no, 'date' => SeparationBook::dmy(substr((string) $l->last_working_date, 0, 10))])];
        }
        if ($e->separation_date) {
            $date = substr((string) $e->separation_date, 0, 10);

            return ['', $date, __('hrpayroll.st.no_record_line', ['date' => SeparationBook::dmy($date)])];
        }

        return ['', SeparationBook::today(), __('hrpayroll.st.on_roster')];
    }

    /** Employees for the settlement form: those to settle first, then everyone else. @return list<array<string, mixed>> */
    private function settleable(): array
    {
        $settled = DB::table('hr_settlements')->where('company_id', $this->caller()->companyId)->pluck('employee_id')->flip();
        $approved = DB::table('hr_separations')->where('company_id', $this->caller()->companyId)->where('status', 'APPROVED')->pluck('employee_id')->flip();
        $all = DB::table('hr_employees')->where('company_id', $this->caller()->companyId)->orderBy('employee_code')->get(['id', 'employee_code', 'name', 'status']);
        $toSettle = fn ($e) => ! isset($settled[$e->id]) && (in_array($e->status, ['SEPARATED', 'INACTIVE'], true) || isset($approved[$e->id]));
        [$first, $rest] = $all->partition($toSettle);

        return $first->map(fn ($e) => ['value' => $e->employee_code, 'label' => __('hrpayroll.st.to_settle', ['who' => "{$e->employee_code} — {$e->name}"])])
            ->concat($rest->map(fn ($e) => ['value' => $e->employee_code, 'label' => "{$e->employee_code} — {$e->name}"]))->values()->all();
    }

    /** @return array<string, mixed> */
    private function validSettlement(Request $request): array
    {
        $rules = ['employee' => ['required', 'string'], 'settlement_type' => ['required', Rule::in(self::SETTLEMENT_TYPES)], 'action_date' => ['required', 'date_format:Y-m-d'], 'remarks' => ['nullable', 'string', 'max:2000']];
        foreach (self::MONEY as $k) {
            $rules[$k] = ['nullable', 'numeric', 'between:-99999999,99999999'];
        }

        return $request->validate($rules, ['employee.required' => __('hrpayroll.st.v.employee'), 'settlement_type.required' => __('hrpayroll.st.v.settlement_type'), 'action_date.required' => __('hrpayroll.st.v.action_date')]);
    }

    /** @param array<string, mixed> $v */
    private function createSettlement(object $e, string $type, string $date, array $v, ?string $remarks, ?string $sep, ?string $lefty): string
    {
        $ref = $this->nextRef('hr_settlements', 'FS', 6);
        $money = array_map(fn ($k) => round((float) ($v[$k] ?? 0), 2), array_combine(self::MONEY, self::MONEY));
        DB::table('hr_settlements')->insert($money + [
            'id' => $this->ulid(), 'company_id' => $this->caller()->companyId, 'ref_no' => $ref, 'employee_id' => $e->id, 'separation_id' => $sep, 'lefty_id' => $lefty,
            'settlement_type' => $type, 'action_date' => $date, 'payable' => SeparationBook::payable($money), 'remarks' => trim((string) $remarks) ?: null, 'notes' => $v['notes'] ?? null,
            'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $ref;
    }

    /** @return list<array<string, mixed>> */
    private function settlementRows(): array
    {
        return DB::table('hr_settlements as f')->join('hr_employees as e', 'e.id', '=', 'f.employee_id')->join('companies as co', 'co.id', '=', 'f.company_id')
            ->leftJoin('designations as d', 'd.id', '=', 'e.designation_id')->leftJoin('departments as dp', 'dp.id', '=', 'e.department_id')->leftJoin('sections as sc', 'sc.id', '=', 'e.section_id')
            ->leftJoin('factories as fa', 'fa.id', '=', 'e.factory_id')->leftJoin('hr_separations as s', 's.id', '=', 'f.separation_id')->leftJoin('hr_lefty_cases as l', 'l.id', '=', 'f.lefty_id')
            ->leftJoin('users as ab', 'ab.id', '=', 'f.approved_by')->leftJoin('users as pb', 'pb.id', '=', 'f.paid_by')
            ->where('f.company_id', $this->caller()->companyId)->orderByDesc('f.ref_no')
            ->get(['f.*', 'e.employee_code', 'e.name', 'e.joining_date', 'e.gross_a', 'd.name as designation', 'dp.name as department', 'sc.name as section', 'co.name as company', 'fa.name as factory',
                's.ref_no as sep_ref', 's.type as sep_type', 'l.ref_no as lefty_ref', 'ab.name as approver', 'pb.name as payer'])
            ->map(function ($f) {
                $date = substr((string) $f->action_date, 0, 10);
                $money = [];
                foreach ([...self::MONEY, 'payable'] as $k) {
                    $money[$k] = (float) $f->{$k};
                }

                return $money + [
                    'ref' => $f->ref_no, 'code' => $f->employee_code, 'name' => $f->name, 'designation' => $f->designation, 'department' => $f->department, 'section' => $f->section,
                    'company' => $f->factory ? "{$f->company} · {$f->factory}" : $f->company, 'joining_date' => $f->joining_date ? substr((string) $f->joining_date, 0, 10) : null, 'gross_a' => (float) $f->gross_a,
                    'separation' => $f->sep_ref ?? $f->lefty_ref, 'separation_type' => $f->sep_type ?? ($f->lefty_ref ? 'LEFTY' : null), 'settlement_type' => $f->settlement_type,
                    'service_length' => SeparationBook::serviceLength($f->joining_date ? substr((string) $f->joining_date, 0, 10) : null, $date), 'action_date' => $date,
                    'dues' => round($money['salary_due'] + $money['ot_due'] + $money['bonus_due'] + $money['leave_encashment'], 2), 'recoveries' => round($money['deduction'] + $money['loan_recovery'], 2),
                    'remarks' => $f->remarks, 'notes' => $f->notes, 'status' => $f->status, 'approved_by' => $f->approver, 'approved_at' => $f->approved_at ? (string) $f->approved_at : null,
                    'paid_by' => $f->payer, 'paid_at' => $f->paid_at ? (string) $f->paid_at : null,
                ];
            })->all();
    }
}
