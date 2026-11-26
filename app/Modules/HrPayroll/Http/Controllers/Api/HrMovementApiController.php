<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Http\Controllers\Api;

use App\Modules\HrPayroll\Support\MovementBook;
use App\Modules\HrPayroll\Support\SalaryStructures;
use App\Shared\Files\Actions\StoreFileAttachment;
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
 * HR & Payroll → Employee Movement (AnnotechERP 24/07), as Brickland lays
 * the four screens out:
 *
 * - **Transfer:** records and a new-transfer form — the employee's old
 *   department, section, line and floor filled in, the new department,
 *   section and line (the floor follows the line), the date, reason and
 *   status. A finalized transfer moves the employee at once; a draft waits
 *   for Finalize on its row and can still be edited or deleted.
 * - **Promotion:** search an employee, ask for a new designation, grade and
 *   (optionally) Gross A with a date and reason; approve or reject with a
 *   note, or withdraw while pending. Approved and due, it takes effect.
 * - **Increment:** the same for a new Gross A — given as the new salary, an
 *   amount or a percent, always above the old one.
 * - **Re-Joining:** a former employee back on their own record from a date,
 *   in a pay period, with the re-join letter and remarks.
 */
class HrMovementApiController extends ApiController
{
    private const MAX_SEARCH = 20;

    public function options(): JsonResponse
    {
        $this->allowEither('hrpayroll.movement.view', 'hrpayroll.movement.manage', 'hrpayroll.movement.approve');
        $c = $this->caller()->companyId;
        $floors = $this->floors();

        return ApiResponse::ok([
            'today' => MovementBook::today(),
            'departments' => DB::table('departments')->where('company_id', $c)->where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name'])->map(fn ($x) => ['value' => $x->id, 'label' => $x->name])->all(),
            'sections' => DB::table('sections')->where('company_id', $c)->where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name', 'department_id'])->map(fn ($x) => ['value' => $x->id, 'label' => $x->name, 'department_id' => $x->department_id])->all(),
            'lines' => DB::table('production_lines')->where('company_id', $c)->where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name', 'section_id', 'department_id', 'floor_id'])
                ->map(fn ($x) => ['value' => $x->id, 'label' => $x->name, 'section_id' => $x->section_id, 'department_id' => $x->department_id, 'floor' => $floors[$x->floor_id] ?? null])->all(),
            'employees' => $this->postedPeople(),
            'can_manage' => $this->caller()->can('hrpayroll.movement.manage'),
        ]);
    }

    /** Employees by code, name, card or NID, with what the movement screens show of them. */
    public function search(Request $request): JsonResponse
    {
        $this->allowEither('hrpayroll.movement.manage', 'hrpayroll.movement.view');
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return ApiResponse::ok([]);
        }
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], Str::lower($q)).'%';
        $rows = $this->people()->whereIn('e.status', ['ACTIVE', 'SUSPENDED'])
            ->where(fn ($w) => $w->whereRaw('lower(e.employee_code) like ?', [$like])->orWhereRaw('lower(e.name) like ?', [$like])->orWhereRaw('lower(e.card_no) like ?', [$like])->orWhereRaw('lower(coalesce(e.nid, \'\')) like ?', [$like]))
            ->limit(self::MAX_SEARCH)->get();

        return ApiResponse::ok($rows->map(fn ($e) => $this->card($e))->values()->all());
    }

    /* ── Transfer ──────────────────────────────────────────────────── */

    public function transfers(): JsonResponse
    {
        $this->allowEither('hrpayroll.movement.view', 'hrpayroll.movement.manage');

        return ApiResponse::ok($this->transferRows());
    }

    public function storeTransfer(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.movement.manage');
        [$e, $values, $finalize] = $this->validTransfer($request, null);
        $ref = $this->nextRef('hr_transfers', 'TRF');
        DB::transaction(function () use ($e, $values, $finalize, $ref): void {
            $id = strtolower((string) Str::ulid());
            DB::table('hr_transfers')->insert($values + ['id' => $id, 'company_id' => $this->caller()->companyId, 'ref_no' => $ref, 'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId(), 'created_at' => now(), 'updated_at' => now()]);
            if ($finalize) {
                $this->finalizeTransfer(DB::table('hr_transfers')->where('id', $id)->first(), $e);
            }
        });

        return ApiResponse::created(['message' => __($finalize ? 'hrpayroll.mv.done.transfer_finalized' : 'hrpayroll.mv.done.transfer_saved', ['ref' => $ref]), 'rows' => $this->transferRows(), 'employees' => $this->postedPeople()]);
    }

    public function updateTransfer(Request $request, string $ref): JsonResponse
    {
        $this->allow('hrpayroll.movement.manage');
        $row = $this->transfer($ref);
        if ($row->status !== 'DRAFT') {
            throw ValidationException::withMessages(['status' => __('hrpayroll.mv.v.finalized')]);
        }
        [$e, $values, $finalize] = $this->validTransfer($request, $row);
        DB::transaction(function () use ($row, $e, $values, $finalize): void {
            DB::table('hr_transfers')->where('id', $row->id)->update($values + ['updated_at' => now()]);
            if ($finalize) {
                $this->finalizeTransfer(DB::table('hr_transfers')->where('id', $row->id)->first(), $e);
            }
        });

        return ApiResponse::ok(['message' => __($finalize ? 'hrpayroll.mv.done.transfer_finalized' : 'hrpayroll.mv.done.transfer_saved', ['ref' => $ref]), 'rows' => $this->transferRows(), 'employees' => $this->postedPeople()]);
    }

    public function finalize(string $ref): JsonResponse
    {
        $this->allow('hrpayroll.movement.manage');
        $row = $this->transfer($ref);
        if ($row->status !== 'DRAFT') {
            throw ValidationException::withMessages(['status' => __('hrpayroll.mv.v.finalized')]);
        }
        $e = DB::table('hr_employees')->where('id', $row->employee_id)->first();
        if (! in_array($e->status, ['ACTIVE', 'SUSPENDED'], true)) {
            throw ValidationException::withMessages(['employee' => __('hrpayroll.mv.v.not_active')]);
        }
        DB::transaction(fn () => $this->finalizeTransfer($row, $e));

        return ApiResponse::ok(['message' => __('hrpayroll.mv.done.transfer_finalized', ['ref' => $ref]), 'rows' => $this->transferRows(), 'employees' => $this->postedPeople()]);
    }

    public function destroyTransfer(string $ref): JsonResponse
    {
        $this->allow('hrpayroll.movement.manage');
        $row = $this->transfer($ref);
        if ($row->status !== 'DRAFT') {
            throw ValidationException::withMessages(['status' => __('hrpayroll.mv.v.finalized')]);
        }
        DB::table('hr_transfers')->where('id', $row->id)->delete();

        return ApiResponse::ok(['message' => __('hrpayroll.mv.done.transfer_deleted', ['ref' => $ref]), 'rows' => $this->transferRows()]);
    }

    /* ── Promotion and Increment ───────────────────────────────────── */

    public function promotions(): JsonResponse
    {
        $this->allowEither('hrpayroll.movement.view', 'hrpayroll.movement.manage', 'hrpayroll.movement.approve');
        (new MovementBook($this->caller()->companyId))->applyDue();
        $c = $this->caller()->companyId;

        return ApiResponse::ok([
            'rows' => $this->promotionRows(),
            'designations' => DB::table('designations as d')->leftJoin('departments as dp', 'dp.id', '=', 'd.department_id')->where('d.company_id', $c)->where('d.status', 'ACTIVE')->orderBy('d.name')->orderBy('dp.name')
                ->get(['d.id', 'd.name', 'dp.name as department'])->map(fn ($x) => ['value' => $x->id, 'label' => $x->department ? "{$x->name} — {$x->department}" : $x->name])->all(),
            'grades' => DB::table('grades')->where('company_id', $c)->orderBy('code')->get(['id', 'code', 'name'])->map(fn ($g) => ['value' => $g->id, 'label' => $g->name ?: $g->code])->all(),
        ] + $this->can());
    }

    public function storePromotion(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.movement.manage');
        $c = $this->caller()->companyId;
        $d = $request->validate([
            'employee' => ['required', 'string'], 'new_designation_id' => ['required', Rule::exists('designations', 'id')->where('company_id', $c)], 'new_grade_id' => ['nullable', Rule::exists('grades', 'id')->where('company_id', $c)],
            'new_gross' => ['nullable', 'numeric', 'min:1', 'max:99999999'], 'effective_date' => ['required', 'date_format:Y-m-d'], 'reason' => ['required', 'string', 'max:1000'],
        ], ['employee.required' => __('hrpayroll.mv.v.employee'), 'new_designation_id.required' => __('hrpayroll.mv.v.designation'), 'effective_date.required' => __('hrpayroll.mv.v.date'), 'reason.required' => __('hrpayroll.mv.v.reason')]);
        $e = $this->activeEmployee($d['employee']);
        if (DB::table('hr_promotions')->where('employee_id', $e->id)->where('status', 'PENDING')->exists()) {
            throw ValidationException::withMessages(['employee' => __('hrpayroll.mv.v.pending_promotion')]);
        }
        $gross = isset($d['new_gross']) ? round((float) $d['new_gross'], 2) : null;
        $grade = $d['new_grade_id'] ?? $e->grade_id;
        if ($d['new_designation_id'] === $e->designation_id && $grade === $e->grade_id && ($gross === null || $gross === round((float) $e->gross_a, 2))) {
            throw ValidationException::withMessages(['new_designation_id' => __('hrpayroll.mv.v.nothing_changes')]);
        }
        if ($gross !== null && (MovementBook::basicOf($e, $gross) ?? 1) <= 0) {
            throw ValidationException::withMessages(['new_gross' => __('hrpayroll.emp.gross_too_low')]);
        }
        $ref = $this->nextRef('hr_promotions', 'PRM');
        DB::table('hr_promotions')->insert([
            'id' => strtolower((string) Str::ulid()), 'company_id' => $c, 'ref_no' => $ref, 'employee_id' => $e->id, 'old_designation_id' => $e->designation_id, 'new_designation_id' => $d['new_designation_id'],
            'old_grade_id' => $e->grade_id, 'new_grade_id' => $grade, 'old_gross' => $e->gross_a, 'new_gross' => $gross, 'effective_date' => $d['effective_date'], 'reason' => trim($d['reason']),
            'status' => 'PENDING', 'requested_by' => $this->caller()->auditUserId(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return ApiResponse::created(['message' => __('hrpayroll.mv.done.sent', ['ref' => $ref]), 'rows' => $this->promotionRows()]);
    }

    public function incrementsIndex(): JsonResponse
    {
        $this->allowEither('hrpayroll.movement.view', 'hrpayroll.movement.manage', 'hrpayroll.movement.approve');
        (new MovementBook($this->caller()->companyId))->applyDue();

        return ApiResponse::ok(['rows' => $this->incrementRows()] + $this->can());
    }

    public function storeIncrement(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.movement.manage');
        $c = $this->caller()->companyId;
        $d = $request->validate([
            'employee' => ['required', 'string'], 'mode' => ['required', Rule::in(['NEW', 'AMOUNT', 'PERCENT'])], 'value' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'effective_date' => ['required', 'date_format:Y-m-d'], 'reason' => ['required', 'string', 'max:1000'],
        ], ['employee.required' => __('hrpayroll.mv.v.employee'), 'value.required' => __('hrpayroll.mv.v.value'), 'value.gt' => __('hrpayroll.mv.v.value'), 'effective_date.required' => __('hrpayroll.mv.v.date'), 'reason.required' => __('hrpayroll.mv.v.reason')]);
        $e = $this->activeEmployee($d['employee']);
        if (DB::table('hr_increments')->where('employee_id', $e->id)->where('status', 'PENDING')->exists()) {
            throw ValidationException::withMessages(['employee' => __('hrpayroll.mv.v.pending_increment')]);
        }
        $old = round((float) $e->gross_a, 2);
        $v = (float) $d['value'];
        $new = match ($d['mode']) {
            'NEW' => round($v, 2),
            'AMOUNT' => round($old + $v, 2),
            default => (float) round($old * (1 + $v / 100)),
        };
        if ($new <= $old) {
            throw ValidationException::withMessages(['value' => __('hrpayroll.mv.v.above_old')]);
        }
        $ref = $this->nextRef('hr_increments', 'INC');
        DB::table('hr_increments')->insert([
            'id' => strtolower((string) Str::ulid()), 'company_id' => $c, 'ref_no' => $ref, 'employee_id' => $e->id, 'mode' => $d['mode'], 'value' => $v, 'old_gross' => $old, 'new_gross' => $new,
            'effective_date' => $d['effective_date'], 'reason' => trim($d['reason']), 'status' => 'PENDING', 'requested_by' => $this->caller()->auditUserId(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return ApiResponse::created(['message' => __('hrpayroll.mv.done.sent', ['ref' => $ref]), 'rows' => $this->incrementRows()]);
    }

    /** Approve or reject a pending promotion or increment, with an optional note. */
    public function decide(Request $request, string $kind, string $ref): JsonResponse
    {
        $this->allow('hrpayroll.movement.approve');
        [$table, $rows] = $this->kind($kind);
        $d = $request->validate(['decision' => ['required', Rule::in(['APPROVE', 'REJECT'])], 'note' => ['nullable', 'string', 'max:1000']]);
        $row = DB::table($table)->where('company_id', $this->caller()->companyId)->where('ref_no', $ref)->first();
        abort_unless($row, 404);
        if ($row->status !== 'PENDING') {
            throw ValidationException::withMessages(['decision' => __('hrpayroll.mv.v.decided')]);
        }
        $status = $d['decision'] === 'APPROVE' ? 'APPROVED' : 'REJECTED';
        DB::table($table)->where('id', $row->id)->update(['status' => $status, 'decision_note' => trim((string) ($d['note'] ?? '')) ?: null, 'decided_by' => $this->caller()->auditUserId(), 'decided_at' => now(), 'updated_at' => now()]);
        $applied = $status === 'APPROVED' ? (new MovementBook($this->caller()->companyId))->applyDue($this->caller()->auditUserId()) : 0;
        $key = $status === 'APPROVED' ? (substr((string) $row->effective_date, 0, 10) <= MovementBook::today() ? 'approved_now' : 'approved_later') : 'rejected';

        return ApiResponse::ok(['message' => __("hrpayroll.mv.done.{$key}", ['ref' => $ref, 'date' => substr((string) $row->effective_date, 0, 10)]), 'applied' => $applied, 'rows' => $this->{$rows}()]);
    }

    /** Withdraw a pending promotion or increment (it is removed). */
    public function withdraw(string $kind, string $ref): JsonResponse
    {
        $this->allow('hrpayroll.movement.manage');
        [$table, $rows] = $this->kind($kind);
        $row = DB::table($table)->where('company_id', $this->caller()->companyId)->where('ref_no', $ref)->first();
        abort_unless($row, 404);
        if ($row->status !== 'PENDING') {
            throw ValidationException::withMessages(['decision' => __('hrpayroll.mv.v.decided')]);
        }
        DB::table($table)->where('id', $row->id)->delete();

        return ApiResponse::ok(['message' => __('hrpayroll.mv.done.withdrawn', ['ref' => $ref]), 'rows' => $this->{$rows}()]);
    }

    /* ── Re-Joining ────────────────────────────────────────────────── */

    public function rejoins(): JsonResponse
    {
        $this->allowEither('hrpayroll.movement.view', 'hrpayroll.movement.manage');
        $c = $this->caller()->companyId;

        return ApiResponse::ok([
            'rows' => $this->rejoinRows(),
            'employees' => $this->leavers(),
            'periods' => DB::table('hr_pay_periods as p')->join('companies as co', 'co.id', '=', 'p.company_id')->where('p.company_id', $c)->where('p.closed', false)->orderByDesc('p.start_date')
                ->get(['p.period_no', 'co.name'])->map(fn ($p) => ['value' => $p->period_no, 'label' => "{$p->period_no} — {$p->name}"])->all(),
            'can_manage' => $this->caller()->can('hrpayroll.movement.manage'),
        ]);
    }

    public function storeRejoin(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.movement.manage');
        $d = $this->validRejoin($request);
        $e = DB::table('hr_employees')->where('company_id', $this->caller()->companyId)->where('employee_code', $d['employee'])->first();
        if (! $e || ! in_array($e->status, ['SEPARATED', 'INACTIVE'], true)) {
            throw ValidationException::withMessages(['employee' => __('hrpayroll.mv.v.not_left')]);
        }
        $left = $e->separation_date ? substr((string) $e->separation_date, 0, 10) : null;
        if ($left && $d['rejoin_date'] <= $left) {
            throw ValidationException::withMessages(['rejoin_date' => __('hrpayroll.mv.v.rejoin_before', ['date' => $left])]);
        }
        $ref = $this->nextRef('hr_rejoins', 'RJN');
        DB::transaction(function () use ($e, $d, $ref, $left): void {
            DB::table('hr_rejoins')->insert($this->rejoinValues($d) + [
                'id' => strtolower((string) Str::ulid()), 'company_id' => $this->caller()->companyId, 'ref_no' => $ref, 'employee_id' => $e->id,
                'previous_status' => $e->status, 'previous_separation_date' => $left, 'created_by' => $this->caller()->auditUserId(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('hr_employees')->where('id', $e->id)->update(['status' => 'ACTIVE', 'active' => true, 'separation_date' => null, 'updated_at' => now()]);
        });

        return ApiResponse::created(['message' => __('hrpayroll.mv.done.rejoined', ['code' => $e->employee_code]), 'ref' => $ref, 'rows' => $this->rejoinRows(), 'employees' => $this->leavers()]);
    }

    public function updateRejoin(Request $request, string $ref): JsonResponse
    {
        $this->allow('hrpayroll.movement.manage');
        $row = $this->rejoin($ref);
        $d = $this->validRejoin($request, true);
        if ($row->previous_separation_date && $d['rejoin_date'] <= substr((string) $row->previous_separation_date, 0, 10)) {
            throw ValidationException::withMessages(['rejoin_date' => __('hrpayroll.mv.v.rejoin_before', ['date' => substr((string) $row->previous_separation_date, 0, 10)])]);
        }
        DB::table('hr_rejoins')->where('id', $row->id)->update($this->rejoinValues($d) + ['updated_at' => now()]);

        return ApiResponse::ok(['message' => __('hrpayroll.mv.done.rejoin_saved', ['ref' => $ref]), 'ref' => $ref, 'rows' => $this->rejoinRows(), 'employees' => $this->leavers()]);
    }

    /** Remove a re-join: the employee goes back to how they had left. */
    public function destroyRejoin(string $ref): JsonResponse
    {
        $this->allow('hrpayroll.movement.manage');
        $row = $this->rejoin($ref);
        DB::transaction(function () use ($row): void {
            DB::table('hr_employees')->where('id', $row->employee_id)->update(['status' => $row->previous_status, 'active' => false, 'separation_date' => $row->previous_separation_date, 'updated_at' => now()]);
            DB::table('hr_rejoins')->where('id', $row->id)->delete();
        });

        return ApiResponse::ok(['message' => __('hrpayroll.mv.done.rejoin_deleted', ['ref' => $ref]), 'rows' => $this->rejoinRows(), 'employees' => $this->leavers()]);
    }

    /** The scanned re-join letter (PDF or image, up to 5 MB). */
    public function rejoinDoc(Request $request, StoreFileAttachment $files, string $ref): JsonResponse
    {
        $this->allow('hrpayroll.movement.manage');
        $row = $this->rejoin($ref);
        $request->validate(['upload' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120']], ['upload.mimes' => __('hrpayroll.mv.v.doc_type'), 'upload.max' => __('hrpayroll.mv.v.doc_size')]);
        $file = $files->handle($request->file('upload'), 'hr_rejoin_doc', $row->id, $this->caller()->auditUserId());
        DB::table('hr_rejoins')->where('id', $row->id)->update(['doc_file_id' => $file->id, 'doc_path' => $request->file('upload')->getClientOriginalName(), 'updated_at' => now()]);

        return ApiResponse::ok(['message' => __('hrpayroll.mv.done.doc_attached'), 'rows' => $this->rejoinRows()]);
    }

    /* ── Helpers ───────────────────────────────────────────────────── */

    /** @return array{can_request: bool, can_approve: bool, today: string} */
    private function can(): array
    {
        return ['can_request' => $this->caller()->can('hrpayroll.movement.manage'), 'can_approve' => $this->caller()->can('hrpayroll.movement.approve'), 'today' => MovementBook::today()];
    }

    /** @return array{0: string, 1: string} */
    private function kind(string $kind): array
    {
        return match ($kind) {
            'promotions' => ['hr_promotions', 'promotionRows'],
            'increments' => ['hr_increments', 'incrementRows'],
            default => abort(404),
        };
    }

    private function people()
    {
        return DB::table('hr_employees as e')->leftJoin('departments as dp', 'dp.id', '=', 'e.department_id')->leftJoin('sections as sc', 'sc.id', '=', 'e.section_id')
            ->leftJoin('production_lines as ln', 'ln.id', '=', 'e.line_id')->leftJoin('designations as d', 'd.id', '=', 'e.designation_id')->leftJoin('grades as g', 'g.id', '=', 'e.grade_id')
            ->where('e.company_id', $this->caller()->companyId)->orderBy('e.employee_code')
            ->select(['e.*', 'dp.name as department', 'sc.name as section', 'ln.name as line', 'd.name as designation', 'g.name as grade_name', 'g.code as grade_code']);
    }

    /** @return array<string, mixed> */
    private function card(object $e): array
    {
        $structure = $e->gazette_id ? SalaryStructures::find($e->gazette_id, $e->employment_type_id) : null;

        return [
            'code' => $e->employee_code, 'name' => $e->name, 'card_no' => $e->card_no, 'designation' => $e->designation, 'designation_id' => $e->designation_id,
            'grade' => $e->grade_name ?: $e->grade_code, 'grade_id' => $e->grade_id, 'department' => $e->department, 'section' => $e->section, 'line' => $e->line,
            'joining_date' => $e->joining_date ? substr((string) $e->joining_date, 0, 10) : null, 'gross_a' => (float) $e->gross_a, 'structure' => $structure['label'] ?? null,
        ];
    }

    /** Floor id => "Floor 2 · Building A". @return array<string, string> */
    private function floors(): array
    {
        return DB::table('floors as f')->leftJoin('buildings as b', 'b.id', '=', 'f.building_id')->where('f.company_id', $this->caller()->companyId)->get(['f.id', 'f.name', 'b.name as building'])
            ->mapWithKeys(fn ($f) => [$f->id => $f->building ? "{$f->name} · {$f->building}" : $f->name])->all();
    }

    /** Active employees with their posting, for the transfer form. @return list<array<string, mixed>> */
    private function postedPeople(): array
    {
        $floors = $this->floors();

        return $this->people()->whereIn('e.status', ['ACTIVE', 'SUSPENDED'])->get()->map(fn ($e) => [
            'value' => $e->employee_code, 'label' => "{$e->employee_code} — {$e->name}",
            'name' => $e->designation ? "{$e->name}, {$e->designation}" : $e->name, 'department' => $e->department, 'section' => $e->section, 'line' => $e->line, 'floor' => $floors[$e->floor_id] ?? null,
            'department_id' => $e->department_id, 'section_id' => $e->section_id, 'line_id' => $e->line_id,
        ])->values()->all();
    }

    /** @return array{0: object, 1: array<string, mixed>, 2: bool} */
    private function validTransfer(Request $request, ?object $row): array
    {
        $c = $this->caller()->companyId;
        $d = $request->validate([
            'employee' => ['required', 'string'], 'new_department_id' => ['required', Rule::exists('departments', 'id')->where('company_id', $c)],
            'new_section_id' => ['nullable', Rule::exists('sections', 'id')->where('company_id', $c)], 'new_line_id' => ['nullable', Rule::exists('production_lines', 'id')->where('company_id', $c)],
            'effective_date' => ['required', 'date_format:Y-m-d'], 'reason' => ['nullable', 'string', 'max:1000'], 'status' => ['required', Rule::in(['DRAFT', 'FINALIZED'])],
        ], ['employee.required' => __('hrpayroll.mv.v.employee'), 'new_department_id.required' => __('hrpayroll.mv.v.department'), 'effective_date.required' => __('hrpayroll.mv.v.date'), 'status.required' => __('hrpayroll.mv.v.status')]);
        $e = $this->activeEmployee($d['employee']);
        if ($row && $row->employee_id !== $e->id) {
            throw ValidationException::withMessages(['employee' => __('hrpayroll.mv.v.employee_fixed')]);
        }
        $section = ! empty($d['new_section_id']) ? DB::table('sections')->where('id', $d['new_section_id'])->first() : null;
        if ($section && $section->department_id !== $d['new_department_id']) {
            throw ValidationException::withMessages(['new_section_id' => __('hrpayroll.mv.v.section_department')]);
        }
        $line = ! empty($d['new_line_id']) ? DB::table('production_lines')->where('id', $d['new_line_id'])->first() : null;
        if ($line && ($section ? ($line->section_id ? $line->section_id !== $section->id : $line->department_id !== $d['new_department_id']) : $line->department_id !== $d['new_department_id'])) {
            throw ValidationException::withMessages(['new_line_id' => __('hrpayroll.mv.v.line_section')]);
        }
        if ($d['new_department_id'] === $e->department_id && ($section->id ?? null) === $e->section_id && ($line->id ?? null) === $e->line_id) {
            throw ValidationException::withMessages(['new_department_id' => __('hrpayroll.mv.v.same_posting')]);
        }
        if (DB::table('hr_transfers')->where('employee_id', $e->id)->where('status', 'DRAFT')->when($row, fn ($q) => $q->where('id', '!=', $row->id))->exists()) {
            throw ValidationException::withMessages(['employee' => __('hrpayroll.mv.v.draft_exists')]);
        }

        return [$e, [
            'employee_id' => $e->id, 'old_department_id' => $e->department_id, 'old_section_id' => $e->section_id, 'old_line_id' => $e->line_id, 'old_floor_id' => $e->floor_id,
            'new_department_id' => $d['new_department_id'], 'new_section_id' => $section->id ?? null, 'new_line_id' => $line->id ?? null, 'new_floor_id' => $line->floor_id ?? null,
            'effective_date' => $d['effective_date'], 'reason' => trim((string) ($d['reason'] ?? '')) ?: null,
        ], $d['status'] === 'FINALIZED'];
    }

    /** Move the employee to the transfer's new posting; the old one is what they leave now. */
    private function finalizeTransfer(object $t, object $e): void
    {
        $floor = $t->new_floor_id ? DB::table('floors')->where('id', $t->new_floor_id)->first() : null;
        $changes = ['department_id' => $t->new_department_id, 'section_id' => $t->new_section_id, 'line_id' => $t->new_line_id];
        if ($t->new_line_id) {
            $changes += ['floor_id' => $t->new_floor_id, 'building_id' => $floor->building_id ?? null];
        }
        (new MovementBook($this->caller()->companyId))->move($e, $changes, substr((string) $t->effective_date, 0, 10), __('hrpayroll.mv.history.transfer', ['ref' => $t->ref_no]).($t->reason ? " — {$t->reason}" : ''), $this->caller()->auditUserId());
        DB::table('hr_transfers')->where('id', $t->id)->update([
            'old_department_id' => $e->department_id, 'old_section_id' => $e->section_id, 'old_line_id' => $e->line_id, 'old_floor_id' => $e->floor_id,
            'status' => 'FINALIZED', 'finalized_by' => $this->caller()->auditUserId(), 'finalized_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function transferRows(): array
    {
        $floors = $this->floors();

        return DB::table('hr_transfers as t')->join('hr_employees as e', 'e.id', '=', 't.employee_id')->leftJoin('designations as d', 'd.id', '=', 'e.designation_id')
            ->leftJoin('departments as od', 'od.id', '=', 't.old_department_id')->leftJoin('sections as os', 'os.id', '=', 't.old_section_id')->leftJoin('production_lines as ol', 'ol.id', '=', 't.old_line_id')
            ->leftJoin('departments as nd', 'nd.id', '=', 't.new_department_id')->leftJoin('sections as ns', 'ns.id', '=', 't.new_section_id')->leftJoin('production_lines as nl', 'nl.id', '=', 't.new_line_id')
            ->leftJoin('users as u', 'u.id', '=', 't.finalized_by')
            ->where('t.company_id', $this->caller()->companyId)->orderByDesc('t.ref_no')
            ->get(['t.*', 'e.employee_code', 'e.name', 'd.name as designation', 'od.name as old_department', 'os.name as old_section', 'ol.name as old_line', 'nd.name as new_department', 'ns.name as new_section', 'nl.name as new_line', 'u.name as finalizer'])
            ->map(fn ($t) => [
                'ref' => $t->ref_no, 'effective_date' => substr((string) $t->effective_date, 0, 10), 'code' => $t->employee_code, 'name' => $t->name, 'designation' => $t->designation,
                'old_department' => $t->old_department, 'old_section' => $t->old_section, 'old_line' => $t->old_line, 'old_floor' => $floors[$t->old_floor_id] ?? null,
                'new_department' => $t->new_department, 'new_section' => $t->new_section, 'new_line' => $t->new_line, 'new_floor' => $floors[$t->new_floor_id] ?? null,
                'new_department_id' => $t->new_department_id, 'new_section_id' => $t->new_section_id, 'new_line_id' => $t->new_line_id,
                'reason' => $t->reason, 'status' => $t->status, 'finalized_by' => $t->finalizer, 'finalized_at' => $t->finalized_at ? (string) $t->finalized_at : null,
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function promotionRows(): array
    {
        return DB::table('hr_promotions as p')->join('hr_employees as e', 'e.id', '=', 'p.employee_id')
            ->leftJoin('designations as od', 'od.id', '=', 'p.old_designation_id')->leftJoin('designations as nd', 'nd.id', '=', 'p.new_designation_id')
            ->leftJoin('grades as og', 'og.id', '=', 'p.old_grade_id')->leftJoin('grades as ng', 'ng.id', '=', 'p.new_grade_id')->leftJoin('users as u', 'u.id', '=', 'p.decided_by')
            ->where('p.company_id', $this->caller()->companyId)->orderByDesc('p.ref_no')
            ->get(['p.*', 'e.employee_code', 'e.name', 'od.name as old_designation', 'nd.name as new_designation', 'og.name as old_grade_name', 'og.code as old_grade_code', 'ng.name as new_grade_name', 'ng.code as new_grade_code', 'u.name as decider'])
            ->map(fn ($p) => [
                'ref' => $p->ref_no, 'code' => $p->employee_code, 'name' => $p->name, 'old_designation' => $p->old_designation, 'new_designation' => $p->new_designation,
                'old_grade' => $p->old_grade_name ?: $p->old_grade_code, 'new_grade' => $p->new_grade_name ?: $p->new_grade_code,
                'old_gross' => (float) $p->old_gross, 'new_gross' => $p->new_gross === null ? null : (float) $p->new_gross,
            ] + $this->decision($p))->all();
    }

    /** @return list<array<string, mixed>> */
    private function incrementRows(): array
    {
        return DB::table('hr_increments as i')->join('hr_employees as e', 'e.id', '=', 'i.employee_id')->leftJoin('designations as d', 'd.id', '=', 'e.designation_id')->leftJoin('users as u', 'u.id', '=', 'i.decided_by')
            ->where('i.company_id', $this->caller()->companyId)->orderByDesc('i.ref_no')
            ->get(['i.*', 'e.employee_code', 'e.name', 'd.name as designation', 'u.name as decider'])
            ->map(fn ($i) => [
                'ref' => $i->ref_no, 'code' => $i->employee_code, 'name' => $i->name, 'designation' => $i->designation, 'mode' => $i->mode, 'value' => (float) $i->value,
                'old_gross' => (float) $i->old_gross, 'new_gross' => (float) $i->new_gross, 'increment' => round((float) $i->new_gross - (float) $i->old_gross, 2),
                'percent' => (float) $i->old_gross > 0 ? round(((float) $i->new_gross - (float) $i->old_gross) / (float) $i->old_gross * 100, 2) : 0.0,
            ] + $this->decision($i))->all();
    }

    /** @return array<string, mixed> */
    private function decision(object $r): array
    {
        return [
            'effective_date' => substr((string) $r->effective_date, 0, 10), 'reason' => $r->reason, 'status' => $r->status, 'decided_by' => $r->decider,
            'decided_at' => $r->decided_at ? (string) $r->decided_at : null, 'decision_note' => $r->decision_note, 'applied_at' => $r->applied_at ? (string) $r->applied_at : null,
        ];
    }

    /** Former employees who can re-join. @return list<array<string, mixed>> */
    private function leavers(): array
    {
        return $this->people()->whereIn('e.status', ['SEPARATED', 'INACTIVE'])->get()->map(fn ($e) => [
            'value' => $e->employee_code, 'label' => "{$e->employee_code} — {$e->name}",
            'summary' => implode(', ', array_filter([$e->name, $e->designation, $e->department, $e->separation_date ? __('hrpayroll.mv.left_on', ['date' => CarbonImmutable::parse($e->separation_date)->format('d M Y')]) : __('hrpayroll.mv.status_word.'.$e->status)])),
        ])->values()->all();
    }

    /** @return array<string, mixed> */
    private function validRejoin(Request $request, bool $editing = false): array
    {
        return $request->validate([
            'employee' => [$editing ? 'nullable' : 'required', 'string'], 'period' => ['nullable', 'string'], 'rejoin_date' => ['required', 'date_format:Y-m-d'],
            'doc_path' => ['nullable', 'string', 'max:500'], 'remarks' => ['nullable', 'string', 'max:2000'], 'remarks_bn' => ['nullable', 'string', 'max:2000'],
        ], ['employee.required' => __('hrpayroll.mv.v.employee_code'), 'rejoin_date.required' => __('hrpayroll.mv.v.rejoin_date')]);
    }

    /** @return array<string, mixed> */
    private function rejoinValues(array $d): array
    {
        $period = ! empty($d['period']) ? DB::table('hr_pay_periods')->where('company_id', $this->caller()->companyId)->where('period_no', $d['period'])->value('id') : null;
        if (! empty($d['period']) && ! $period) {
            throw ValidationException::withMessages(['period' => __('hrpayroll.mv.v.period')]);
        }

        return ['pay_period_id' => $period, 'rejoin_date' => $d['rejoin_date'], 'doc_path' => trim((string) ($d['doc_path'] ?? '')) ?: null, 'remarks' => trim((string) ($d['remarks'] ?? '')) ?: null, 'remarks_bn' => trim((string) ($d['remarks_bn'] ?? '')) ?: null];
    }

    /** @return list<array<string, mixed>> */
    private function rejoinRows(): array
    {
        return DB::table('hr_rejoins as r')->join('hr_employees as e', 'e.id', '=', 'r.employee_id')->leftJoin('designations as d', 'd.id', '=', 'e.designation_id')->leftJoin('departments as dp', 'dp.id', '=', 'e.department_id')
            ->leftJoin('hr_pay_periods as p', 'p.id', '=', 'r.pay_period_id')->where('r.company_id', $this->caller()->companyId)->orderByDesc('r.ref_no')
            ->get(['r.*', 'e.employee_code', 'e.name', 'd.name as designation', 'dp.name as department', 'p.period_no'])
            ->map(fn ($r) => [
                'ref' => $r->ref_no, 'code' => $r->employee_code, 'name' => $r->name, 'designation' => $r->designation, 'summary' => implode(', ', array_filter([$r->name, $r->designation, $r->department])),
                'period' => $r->period_no, 'rejoin_date' => substr((string) $r->rejoin_date, 0, 10), 'doc_path' => $r->doc_path, 'doc_file_id' => $r->doc_file_id,
                'remarks' => $r->remarks, 'remarks_bn' => $r->remarks_bn, 'left_on' => $r->previous_separation_date ? substr((string) $r->previous_separation_date, 0, 10) : null,
            ])->all();
    }

    private function activeEmployee(string $code): object
    {
        $e = DB::table('hr_employees')->where('company_id', $this->caller()->companyId)->where('employee_code', $code)->first();
        if (! $e) {
            throw ValidationException::withMessages(['employee' => __('hrpayroll.mv.v.employee')]);
        }
        if (! in_array($e->status, ['ACTIVE', 'SUSPENDED'], true)) {
            throw ValidationException::withMessages(['employee' => __('hrpayroll.mv.v.not_active')]);
        }

        return $e;
    }

    private function transfer(string $ref): object
    {
        $row = DB::table('hr_transfers')->where('company_id', $this->caller()->companyId)->where('ref_no', $ref)->first();
        abort_unless($row, 404);

        return $row;
    }

    private function rejoin(string $ref): object
    {
        $row = DB::table('hr_rejoins')->where('company_id', $this->caller()->companyId)->where('ref_no', $ref)->first();
        abort_unless($row, 404);

        return $row;
    }

    private function nextRef(string $table, string $prefix): string
    {
        $last = DB::table($table)->where('company_id', $this->caller()->companyId)->orderByDesc('ref_no')->value('ref_no');

        return $prefix.'-'.str_pad((string) ((int) substr((string) $last, strlen($prefix) + 1) + 1), 6, '0', STR_PAD_LEFT);
    }
}
