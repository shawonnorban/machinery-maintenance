<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Http\Controllers\Api;

use App\Modules\HrPayroll\Models\HrShift;
use App\Modules\HrPayroll\Models\HrEmployee;
use App\Modules\HrPayroll\Models\HrEmployeeAssignment;
use App\Modules\HrPayroll\Models\HrEmployeeSalary;
use App\Modules\HrPayroll\Support\HrDefaults;
use App\Modules\HrPayroll\Support\SalaryStructures;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Department;
use App\Modules\Tenancy\Models\Designation;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\Floor;
use App\Modules\Tenancy\Models\Grade;
use App\Modules\Tenancy\Models\ProductionLine;
use App\Modules\Tenancy\Models\Section;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * HR & Payroll → Employee (AnnotechERP 24/03), as Brickland keeps it.
 * Quick Registration: the auto-numbered code, card / proxy number, name, NID
 * and the basic identity, the salary structure with Gross A, Gross B and the
 * actual salary — saved without a posting. Employee List: everybody,
 * searched and filtered, with every column the profile fills. Employee
 * Assignment: a posting for one or several at once — factory, building,
 * floor, department, section, line, designation, grade and shift, every one
 * of them from Company Management; a transfer closes the current posting
 * today and keeps it in the history.
 */
class HrEmployeeApiController extends ApiController
{
    private const PER_PAGE = 25;

    /** What Quick Registration and Employee Assignment pick from. */
    public function options(): JsonResponse
    {
        $this->allow('hrpayroll.employee.view');
        // The gazettes and categories a structure is made of arrive with the company's HR defaults.
        HrDefaults::ensure(app(TenantContext::class)->companyId());
        $active = fn (Builder $q): Builder => $q->where('status', 'ACTIVE')->orderBy('name');

        return ApiResponse::ok([
            'next_code' => self::code($this->lastNumber() + 1),
            'structures' => SalaryStructures::all(),
            'statuses' => HrEmployee::STATUSES,
            'recent' => $this->rows(HrEmployee::query()->orderByDesc('created_at')->orderByDesc('employee_code')->limit(5)->get()),
            'factories' => $active(Factory::query())->get(['id', 'name', 'code'])->map(fn ($f) => ['value' => $f->id, 'label' => $f->name])->all(),
            'buildings' => $active(Building::query())->get(['id', 'name', 'factory_id'])->map(fn ($b) => ['value' => $b->id, 'label' => $b->name, 'factory_id' => $b->factory_id])->all(),
            'floors' => $active(Floor::query())->get(['id', 'name', 'building_id'])->map(fn ($f) => ['value' => $f->id, 'label' => $f->name, 'building_id' => $f->building_id])->all(),
            'departments' => $active(Department::query())->get(['id', 'name', 'factory_id'])->map(fn ($d) => ['value' => $d->id, 'label' => $d->name, 'factory_id' => $d->factory_id])->all(),
            'sections' => $active(Section::query())->get(['id', 'name', 'department_id', 'floor_id'])->map(fn ($s) => ['value' => $s->id, 'label' => $s->name, 'department_id' => $s->department_id, 'floor_id' => $s->floor_id])->all(),
            'lines' => $active(ProductionLine::query())->get(['id', 'name', 'floor_id', 'section_id', 'department_id'])->map(fn ($l) => ['value' => $l->id, 'label' => $l->name, 'floor_id' => $l->floor_id, 'section_id' => $l->section_id, 'department_id' => $l->department_id])->all(),
            'designations' => $active(Designation::query())->get(['id', 'name', 'department_id'])->map(fn ($d) => ['value' => $d->id, 'label' => $d->name, 'department_id' => $d->department_id])->all(),
            'grades' => Grade::query()->where('status', 'ACTIVE')->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])->map(fn ($g) => ['value' => $g->id, 'label' => $g->name])->all(),
            'shifts' => HrShift::query()->where('active', true)->orderBy('start_time')->get(['id', 'name', 'start_time', 'end_time'])->map(fn ($s) => ['value' => $s->id, 'label' => $s->name.' ('.\App\Shared\Support\Clock::range(substr((string) $s->start_time, 0, 5), substr((string) $s->end_time, 0, 5)).')'])->all(),
        ]);
    }

    /** Employee List and Employee Assignment's list: searched, filtered, a page at a time. */
    public function index(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.employee.view');
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(HrEmployee::STATUSES)], 'assignment' => ['nullable', Rule::in(['ASSIGNED', 'PENDING'])],
            'factory' => ['nullable', 'string'], 'department' => ['nullable', 'string'], 'section' => ['nullable', 'string'],
            'line' => ['nullable', 'string'], 'designation' => ['nullable', 'string'], 'shift' => ['nullable', 'string'],
        ]);
        $query = $this->filtered($f);
        $total = (clone $query)->count();
        $page = (int) ($f['page'] ?? 1);
        $rows = $query->orderByDesc('employee_code')->forPage($page, self::PER_PAGE)->get();
        $all = $this->filtered(array_diff_key($f, ['assignment' => true]));

        return ApiResponse::ok([
            'rows' => $this->rows($rows), 'total' => $total, 'page' => $page, 'per_page' => self::PER_PAGE,
            'counts' => ['pending' => $this->pending(clone $all)->count(), 'assigned' => $this->assigned(clone $all)->count(), 'all' => (clone $all)->count()],
        ]);
    }

    public function show(string $employee): JsonResponse
    {
        $this->allow('hrpayroll.employee.view');

        return ApiResponse::ok($this->rows(new Collection([$this->find($employee)]))[0]);
    }

    /** Quick Registration: a new employee, numbered on save, without a posting. */
    public function store(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.employee.manage');
        $data = $this->validated($request, null);
        $row = DB::transaction(function () use ($data): HrEmployee {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ['hr_employees:'.app(TenantContext::class)->companyId()]);
            $row = HrEmployee::query()->create($data + ['employee_code' => self::code($this->lastNumber() + 1), 'created_by' => $this->caller()->auditUserId()]);
            $this->recordSalary($row);

            return $row;
        });

        return ApiResponse::created($this->rows(new Collection([$row]))[0]);
    }

    /** Edit: the same fields; a changed salary is kept as a new salary record. */
    public function update(Request $request, string $employee): JsonResponse
    {
        $this->allow('hrpayroll.employee.manage');
        $row = $this->find($employee);
        $data = $this->validated($request, $row);
        DB::transaction(function () use ($row, $data): void {
            $before = [$row->gazette_id, $row->employment_type_id, (float) $row->gross_a, (float) $row->gross_b, $row->actual_salary === null ? null : (float) $row->actual_salary];
            $row->update($data);
            $after = [$row->gazette_id, $row->employment_type_id, (float) $row->gross_a, (float) $row->gross_b, $row->actual_salary === null ? null : (float) $row->actual_salary];
            if ($before !== $after) {
                $this->recordSalary($row, now()->toDateString());
            }
        });

        return ApiResponse::ok($this->rows(new Collection([$row->fresh()]))[0]);
    }

    /**
     * Employee Assignment: one placement for one employee or several. Whoever
     * is already posted is transferred — their current posting closes today,
     * with the reason, and stays in the history.
     */
    public function assign(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.employee.manage');
        $company = app(TenantContext::class)->companyId();
        $in = fn (string $table) => Rule::exists($table, 'id')->where('company_id', $company);
        $data = $request->validate([
            'employees' => ['required', 'array', 'min:1', 'max:500'], 'employees.*' => ['string'],
            'factory_id' => ['required', $in('factories')], 'building_id' => ['nullable', $in('buildings')], 'floor_id' => ['nullable', $in('floors')],
            'department_id' => ['required', $in('departments')], 'section_id' => ['nullable', $in('sections')], 'line_id' => ['nullable', $in('production_lines')],
            'designation_id' => ['required', $in('designations')], 'grade_id' => ['nullable', $in('grades')], 'shift_id' => ['nullable', $in('hr_shifts')],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'employees.required' => __('hrpayroll.emp.pick_employee'), 'factory_id.required' => __('hrpayroll.emp.factory_required'),
            'department_id.required' => __('hrpayroll.emp.department_required'), 'designation_id.required' => __('hrpayroll.emp.designation_required'),
        ]);
        $people = HrEmployee::query()->whereIn('employee_code', $data['employees'])->get();
        if ($people->count() !== count(array_unique($data['employees']))) {
            throw ValidationException::withMessages(['employees' => __('hrpayroll.emp.pick_employee')]);
        }
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '' && $people->contains(fn (HrEmployee $e): bool => $e->isAssigned())) {
            throw ValidationException::withMessages(['reason' => __('hrpayroll.emp.reason_required')]);
        }
        $placement = array_intersect_key($data, array_flip(HrEmployee::PLACEMENT)) + array_fill_keys(HrEmployee::PLACEMENT, null);
        $today = now()->toDateString();
        DB::transaction(function () use ($people, $placement, $reason, $today): void {
            foreach ($people as $e) {
                HrEmployeeAssignment::query()->where('employee_id', $e->id)->whereNull('effective_to')->update(['effective_to' => $today]);
                HrEmployeeAssignment::query()->create(['employee_id' => $e->id, 'reason' => $reason ?: null, 'effective_from' => $today, 'created_by' => $this->caller()->auditUserId()] + $placement);
                $e->update($placement);
            }
        });

        return ApiResponse::ok(['assigned' => $people->count(), 'rows' => $this->rows($people->map->fresh())]);
    }

    /** History: every posting and every salary, newest first. */
    public function history(string $employee): JsonResponse
    {
        $this->allow('hrpayroll.employee.view');
        $row = $this->find($employee);
        $postings = HrEmployeeAssignment::query()->where('employee_id', $row->id)->orderByDesc('effective_from')->orderByDesc('created_at')->get();
        $names = $this->names($postings);
        $structures = collect(SalaryStructures::all())->keyBy('value');

        return ApiResponse::ok([
            'employee' => $this->rows(new Collection([$row]))[0],
            'assignments' => $postings->map(fn (HrEmployeeAssignment $a): array => [
                'from' => $a->effective_from->toDateString(), 'to' => $a->effective_to?->toDateString(), 'reason' => $a->reason,
            ] + $this->placementNames($a, $names))->values()->all(),
            'salaries' => HrEmployeeSalary::query()->where('employee_id', $row->id)->orderByDesc('effective_from')->orderByDesc('created_at')->get()
                ->map(fn (HrEmployeeSalary $s): array => [
                    'from' => $s->effective_from->toDateString(), 'structure' => $structures["{$s->gazette_id}:{$s->employment_type_id}"]['label'] ?? null,
                    'gross_a' => (float) $s->gross_a, 'gross_b' => (float) $s->gross_b, 'actual' => $s->actual_salary === null ? null : (float) $s->actual_salary,
                ])->values()->all(),
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?HrEmployee $row): array
    {
        $company = app(TenantContext::class)->companyId();
        $request->merge(['card_no' => trim((string) $request->input('card_no')), 'nid' => preg_replace('/\s+/', '', (string) $request->input('nid'))]);
        $data = $request->validate([
            'card_no' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:255'],
            'nid' => ['required', 'regex:/^(\d{10}|\d{13}|\d{17})$/'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'joining_date' => ['nullable', 'date'],
            'mobile' => ['nullable', 'regex:/^01\d{9}$/'],
            'status' => ['nullable', Rule::in(HrEmployee::STATUSES)],
            'structure' => ['required', 'string'],
            'gross_a' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'gross_b' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'actual_salary' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ], [
            'card_no.required' => __('hrpayroll.emp.card_required'), 'name.required' => __('hrpayroll.emp.name_required'),
            'nid.required' => __('hrpayroll.emp.nid_required'), 'nid.regex' => __('hrpayroll.emp.nid_digits'),
            'mobile.regex' => __('hrpayroll.emp.mobile_format'), 'date_of_birth.before' => __('hrpayroll.emp.dob_past'),
            'structure.required' => __('hrpayroll.emp.structure_required'),
            'gross_a.required' => __('hrpayroll.emp.gross_a_required'), 'gross_a.gt' => __('hrpayroll.emp.gross_a_required'),
            'gross_b.required' => __('hrpayroll.emp.gross_b_required'), 'gross_b.gt' => __('hrpayroll.emp.gross_b_required'),
        ]);
        [$gazette, $type] = array_pad(explode(':', $data['structure']), 2, '');
        $structure = SalaryStructures::find($gazette, $type) ?? throw ValidationException::withMessages(['structure' => __('hrpayroll.emp.structure_required')]);
        $taken = HrEmployee::query()->where('company_id', $company)->where('card_no', $data['card_no'])->when($row, fn ($q) => $q->whereKeyNot($row->id))->value('employee_code');
        if ($taken) {
            throw ValidationException::withMessages(['card_no' => __('hrpayroll.emp.card_taken', ['card' => $data['card_no'], 'code' => $taken])]);
        }
        // Gross must at least cover the fixed heads, or Basic would come out at nothing.
        foreach (['gross_a', 'gross_b'] as $k) {
            if (SalaryStructures::split($structure['heads'], (float) $data[$k])['basic'] <= 0) {
                throw ValidationException::withMessages([$k => __('hrpayroll.emp.gross_too_low')]);
            }
        }
        unset($data['structure']);

        return $data + ['gazette_id' => $gazette, 'employment_type_id' => $type, 'status' => $data['status'] ?? 'ACTIVE', 'mobile' => $data['mobile'] ?? null];
    }

    protected function recordSalary(HrEmployee $row, ?string $from = null): void
    {
        HrEmployeeSalary::query()->create([
            'employee_id' => $row->id, 'gazette_id' => $row->gazette_id, 'employment_type_id' => $row->employment_type_id,
            'gross_a' => $row->gross_a, 'gross_b' => $row->gross_b, 'actual_salary' => $row->actual_salary,
            'effective_from' => $from ?? ($row->joining_date?->toDateString() ?? now()->toDateString()), 'created_by' => $this->caller()->auditUserId(),
        ]);
    }

    protected function find(string $code): HrEmployee
    {
        return HrEmployee::query()->where('employee_code', $code)->firstOrFail();
    }

    /** @param array<string, mixed> $f */
    private function filtered(array $f): Builder
    {
        $q = trim((string) ($f['q'] ?? ''));

        return HrEmployee::query()
            ->when($q !== '', fn (Builder $b) => $b->where(fn (Builder $w) => $w->where('employee_code', 'ilike', "%{$q}%")->orWhere('name', 'ilike', "%{$q}%")
                ->orWhere('card_no', 'ilike', "%{$q}%")->orWhere('nid', 'ilike', "%{$q}%")->orWhere('mobile', 'ilike', "%{$q}%")))
            ->when($f['status'] ?? null, fn (Builder $b, string $v) => $b->where('status', $v))
            ->when(($f['assignment'] ?? null) === 'ASSIGNED', fn (Builder $b) => $this->assigned($b))
            ->when(($f['assignment'] ?? null) === 'PENDING', fn (Builder $b) => $this->pending($b))
            ->when($f['factory'] ?? null, fn (Builder $b, string $v) => $b->where('factory_id', $v))
            ->when($f['department'] ?? null, fn (Builder $b, string $v) => $b->where('department_id', $v))
            ->when($f['section'] ?? null, fn (Builder $b, string $v) => $b->where('section_id', $v))
            ->when($f['line'] ?? null, fn (Builder $b, string $v) => $b->where('line_id', $v))
            ->when($f['designation'] ?? null, fn (Builder $b, string $v) => $b->where('designation_id', $v))
            ->when($f['shift'] ?? null, fn (Builder $b, string $v) => $b->where('shift_id', $v));
    }

    private function assigned(Builder $b): Builder
    {
        return $b->whereNotNull('factory_id')->whereNotNull('department_id')->whereNotNull('designation_id');
    }

    private function pending(Builder $b): Builder
    {
        return $b->where(fn (Builder $w) => $w->whereNull('factory_id')->orWhereNull('department_id')->orWhereNull('designation_id'));
    }

    /** @return list<array<string, mixed>> */
    protected function rows(\Illuminate\Support\Collection $rows): array
    {
        $names = $this->names($rows);
        $structures = collect(SalaryStructures::all())->keyBy('value');
        $starting = HrEmployeeSalary::query()->whereIn('employee_id', $rows->pluck('id'))->orderBy('effective_from')->orderBy('created_at')->get()->groupBy('employee_id')->map->first();

        return $rows->map(function (HrEmployee $e) use ($names, $structures, $starting): array {
            $structure = $structures["{$e->gazette_id}:{$e->employment_type_id}"] ?? null;

            return [
                'code' => $e->employee_code, 'card_no' => $e->card_no, 'name' => $e->name, 'nid' => $e->nid,
                'date_of_birth' => $e->date_of_birth?->toDateString(), 'joining_date' => $e->joining_date?->toDateString(), 'mobile' => $e->mobile, 'status' => $e->status,
                'structure' => $structure ? $structure['value'] : "{$e->gazette_id}:{$e->employment_type_id}", 'structure_label' => $structure['label'] ?? null,
                'gross_a' => (float) $e->gross_a, 'gross_b' => (float) $e->gross_b, 'actual_salary' => $e->actual_salary === null ? null : (float) $e->actual_salary,
                'starting_salary' => isset($starting[$e->id]) ? (float) $starting[$e->id]->gross_a : null,
                'split_a' => $structure ? SalaryStructures::split($structure['heads'], (float) $e->gross_a) : null,
                'split_b' => $structure ? SalaryStructures::split($structure['heads'], (float) $e->gross_b) : null,
                'father_name' => $e->father_name, 'mother_name' => $e->mother_name, 'email' => $e->email, 'gender' => $e->gender, 'religion' => $e->religion,
                'blood_group' => $e->blood_group, 'marital_status' => $e->marital_status, 'country' => $e->country, 'rocket_no' => $e->rocket_no,
                'present_address' => $e->present_address, 'permanent_address' => $e->permanent_address,
                'assigned' => $e->isAssigned(), 'placement' => array_intersect_key($e->only(HrEmployee::PLACEMENT), array_flip(HrEmployee::PLACEMENT)),
            ] + $this->placementNames($e, $names);
        })->values()->all();
    }

    /** The names of every place the rows are posted to, by table. @return array<string, array<string, string>> */
    protected function names(\Illuminate\Support\Collection $rows): array
    {
        $ids = fn (string $col) => $rows->pluck($col)->filter()->unique()->values()->all();

        return [
            'factory' => Factory::query()->whereIn('id', $ids('factory_id'))->pluck('name', 'id')->all(),
            'building' => Building::query()->whereIn('id', $ids('building_id'))->pluck('name', 'id')->all(),
            'floor' => Floor::query()->whereIn('id', $ids('floor_id'))->pluck('name', 'id')->all(),
            'department' => Department::query()->whereIn('id', $ids('department_id'))->pluck('name', 'id')->all(),
            'section' => Section::query()->whereIn('id', $ids('section_id'))->pluck('name', 'id')->all(),
            'line' => ProductionLine::query()->whereIn('id', $ids('line_id'))->pluck('name', 'id')->all(),
            'designation' => Designation::query()->whereIn('id', $ids('designation_id'))->pluck('name', 'id')->all(),
            'grade' => Grade::query()->whereIn('id', $ids('grade_id'))->pluck('name', 'id')->all(),
            'shift' => HrShift::query()->whereIn('id', $ids('shift_id'))->pluck('name', 'id')->all(),
        ];
    }

    /** @return array<string, ?string> */
    protected function placementNames(object $row, array $names): array
    {
        $out = [];
        foreach ($names as $kind => $map) {
            $id = $row->{"{$kind}_id"};
            $out[$kind] = $id ? ($map[$id] ?? null) : null;
        }

        return $out;
    }

    protected function lastNumber(): int
    {
        return (int) HrEmployee::query()->where('employee_code', 'like', 'EMP-%')->pluck('employee_code')
            ->map(fn (string $c): int => (int) substr($c, 4))->max();
    }

    protected static function code(int $n): string
    {
        return 'EMP-'.str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }
}
