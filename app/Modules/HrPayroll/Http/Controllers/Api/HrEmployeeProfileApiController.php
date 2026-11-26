<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Http\Controllers\Api;

use App\Modules\HrPayroll\Models\HrEmployee;
use App\Modules\HrPayroll\Models\HrEmployeeAssignment;
use App\Modules\HrPayroll\Models\HrEmploymentType;
use App\Modules\HrPayroll\Models\HrPayElement;
use App\Modules\HrPayroll\Support\HrDefaults;
use App\Modules\HrPayroll\Support\SalaryStructures;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Files\Actions\StoreFileAttachment;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * HR & Payroll → Employee → Employee Profile (AnnotechERP 24/03), as Brickland
 * keeps an employee's full record: the pinned header (code, Proxi#, company,
 * office, photo) and seven tabs — Personal Information, Job Detail, Contact
 * Detail, Document, Allowance/Deduction, Optional and Back Ground Check —
 * written in one save, so a half-entered employee never reaches the database.
 *
 * Job Detail's posting goes through the same history Employee Assignment
 * keeps: a changed posting closes the current one today and opens the new
 * one. A changed gross or employee type is kept as a new salary record. An
 * employee already used on another record (a gate pass, a production entry,
 * a bill …) cannot be removed.
 */
class HrEmployeeProfileApiController extends HrEmployeeApiController
{
    public const RELIGIONS = ['ISLAM', 'HINDUISM', 'CHRISTIANITY', 'BUDDHISM', 'OTHER'];

    public const GENDERS = ['MALE', 'FEMALE', 'OTHER'];

    public const MARITAL = ['SINGLE', 'MARRIED', 'DIVORCED', 'WIDOWED'];

    public const BLOOD = ['A_POS', 'A_NEG', 'B_POS', 'B_NEG', 'AB_POS', 'AB_NEG', 'O_POS', 'O_NEG'];

    public const RELATIONS = ['FATHER', 'MOTHER', 'SPOUSE', 'SON', 'DAUGHTER', 'BROTHER', 'SISTER', 'UNCLE', 'AUNT', 'OTHER'];

    public const NATIONALITIES = ['Bangladesh', 'India', 'Pakistan', 'Sri Lanka', 'Nepal', 'Bhutan', 'Myanmar', 'China', 'Hong Kong', 'Taiwan', 'Japan', 'South Korea', 'Vietnam', 'Thailand', 'Malaysia', 'Singapore', 'Indonesia', 'Philippines', 'Turkey', 'United Arab Emirates', 'United Kingdom', 'Germany', 'Belgium', 'Netherlands', 'Italy', 'Spain', 'France', 'Bulgaria', 'United States', 'Canada', 'Brazil', 'Australia'];

    /** The tables that are the employee's own record — everything else pointing at an employee is a use of them. */
    private const OWN = ['hr_employee_assignments', 'hr_employee_salaries', 'hr_employee_pay_lines', 'hr_employee_background_checks'];

    /** Job Detail's posting, with the office standing for the factory. */
    private const POSTING = ['building_id', 'floor_id', 'department_id', 'section_id', 'line_id', 'designation_id', 'grade_id', 'shift_id'];

    /** What the profile picks from, beyond Employee Assignment's places (`/employees/options`). */
    public function profileOptions(): JsonResponse
    {
        $this->allow('hrpayroll.employee.view');
        $company = app(TenantContext::class)->companyId();
        HrDefaults::ensure($company);
        $types = HrEmploymentType::query()->pluck('name', 'id');

        return ApiResponse::ok([
            'next_code' => self::code($this->lastNumber() + 1),
            'companies' => [['value' => $company, 'label' => (string) DB::table('companies')->where('id', $company)->value('name')]],
            'employee_types' => collect(SalaryStructures::all())->pluck('employment_type_id')->unique()->map(fn ($id) => ['value' => $id, 'label' => $types[$id] ?? '—'])->values()->all(),
            'pay_elements' => HrPayElement::query()->where('active', true)->orderBy('code')->get(['id', 'code', 'name'])->map(fn ($p) => ['value' => $p->id, 'label' => $p->name, 'code' => $p->code])->all(),
            'nationalities' => array_map(fn ($n) => ['value' => $n, 'label' => $n], self::NATIONALITIES),
            'religions' => self::RELIGIONS, 'genders' => self::GENDERS, 'marital' => self::MARITAL, 'blood' => self::BLOOD, 'relations' => self::RELATIONS, 'statuses' => HrEmployee::STATUSES,
        ]);
    }

    /** Records: the roster, narrowed by the Search panel (department, section, status, part of a code). */
    public function profiles(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.employee.view');
        $f = $request->validate(['department' => ['nullable', 'string'], 'section' => ['nullable', 'string'], 'status' => ['nullable', Rule::in(HrEmployee::STATUSES)], 'code' => ['nullable', 'string', 'max:40']]);
        $rows = HrEmployee::query()
            ->when($f['department'] ?? null, fn (Builder $b, string $v) => $b->where('department_id', $v))
            ->when($f['section'] ?? null, fn (Builder $b, string $v) => $b->where('section_id', $v))
            ->when($f['status'] ?? null, fn (Builder $b, string $v) => $b->where('status', $v))
            ->when(trim((string) ($f['code'] ?? '')) !== '', fn (Builder $b) => $b->where('employee_code', 'ilike', '%'.trim((string) $f['code']).'%'))
            ->orderByDesc('employee_code')->limit(2000)->get();
        $names = $this->names($rows);
        $business = $this->businessTypes();

        return ApiResponse::ok($rows->map(fn (HrEmployee $e): array => [
            'code' => $e->employee_code, 'card_no' => $e->card_no, 'name' => $e->name, 'status' => $e->status, 'assigned' => $e->isAssigned(),
            'business_type' => $business[$e->employment_type_id] ?? null, 'photo_file_id' => $e->photo_file_id,
        ] + $this->placementNames($e, $names))->values()->all());
    }

    public function profile(string $employee): JsonResponse
    {
        $this->allow('hrpayroll.employee.view');

        return ApiResponse::ok($this->body($this->find($employee)));
    }

    /** New employee: the whole profile in one save, numbered on save. */
    public function storeProfile(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.employee.manage');
        [$data, $lines, $checks] = $this->validatedProfile($request, null);
        $row = DB::transaction(function () use ($data, $lines, $checks): HrEmployee {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ['hr_employees:'.app(TenantContext::class)->companyId()]);
            $row = HrEmployee::query()->create($data + ['employee_code' => self::code($this->lastNumber() + 1), 'gross_b' => $data['gross_a'], 'created_by' => $this->caller()->auditUserId()]);
            $this->recordSalary($row);
            if ($row->department_id && $row->designation_id) {
                HrEmployeeAssignment::query()->create(['employee_id' => $row->id, 'effective_from' => $row->joining_date?->toDateString() ?? now()->toDateString(), 'created_by' => $this->caller()->auditUserId()] + $row->only(HrEmployee::PLACEMENT));
            }
            $this->saveLines($row, $lines);
            $this->saveChecks($row, $checks);

            return $row;
        });

        return ApiResponse::created($this->body($row->fresh()));
    }

    /** Edit employee: the same tabs; a changed posting or salary goes to the history. */
    public function updateProfile(Request $request, string $employee): JsonResponse
    {
        $this->allow('hrpayroll.employee.manage');
        $row = $this->find($employee);
        [$data, $lines, $checks] = $this->validatedProfile($request, $row);
        DB::transaction(function () use ($row, $data, $lines, $checks): void {
            $salary = fn () => [$row->gazette_id, $row->employment_type_id, (float) $row->gross_a];
            $posting = fn () => $row->only(HrEmployee::PLACEMENT);
            [$beforeSalary, $beforePosting] = [$salary(), $posting()];
            $row->update($data);
            if ($beforeSalary !== $salary()) {
                $this->recordSalary($row, now()->toDateString());
            }
            if ($beforePosting != $posting()) {
                $today = now()->toDateString();
                HrEmployeeAssignment::query()->where('employee_id', $row->id)->whereNull('effective_to')->update(['effective_to' => $today]);
                if ($row->department_id && $row->designation_id) {
                    HrEmployeeAssignment::query()->create(['employee_id' => $row->id, 'reason' => __('hrpayroll.emp.prof.reason'), 'effective_from' => $today, 'created_by' => $this->caller()->auditUserId()] + $row->only(HrEmployee::PLACEMENT));
                }
            }
            $this->saveLines($row, $lines);
            $this->saveChecks($row, $checks);
        });

        return ApiResponse::ok($this->body($row->fresh()));
    }

    /** Remove an employee nobody else's record uses yet; their own history goes with them. */
    public function destroyProfile(string $employee): JsonResponse
    {
        $this->allow('hrpayroll.employee.manage');
        $row = $this->find($employee);
        $uses = 0;
        $refs = DB::select("select c.conrelid::regclass::text as tbl, a.attname as col from pg_constraint c join pg_attribute a on a.attrelid = c.conrelid and a.attnum = any(c.conkey) where c.contype = 'f' and c.confrelid = 'hr_employees'::regclass");
        foreach ($refs as $r) {
            if (! in_array($r->tbl, self::OWN, true)) {
                $uses += DB::table($r->tbl)->where($r->col, $row->id)->count();
            }
        }
        if ($uses > 0) {
            throw ValidationException::withMessages(['employee' => __('hrpayroll.emp.prof.in_use', ['code' => $row->employee_code, 'n' => $uses])]);
        }
        $row->delete();

        return ApiResponse::ok(['deleted' => $row->employee_code]);
    }

    /** The photograph on the profile (JPG or PNG, up to 2 MB), shown beside the header and on the printed profile. */
    public function photo(Request $request, StoreFileAttachment $files, string $employee): JsonResponse
    {
        $this->allow('hrpayroll.employee.manage');
        $row = $this->find($employee);
        $request->validate(['upload' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:2048']], ['upload.mimes' => __('hrpayroll.emp.prof.photo_type'), 'upload.max' => __('hrpayroll.emp.prof.photo_size')]);
        $file = $files->handle($request->file('upload'), 'hr_employee_photo', $row->id, $this->caller()->auditUserId());
        $row->update(['photo_file_id' => $file->id]);

        return ApiResponse::ok($this->body($row->fresh()));
    }

    public function removePhoto(string $employee): JsonResponse
    {
        $this->allow('hrpayroll.employee.manage');
        $row = $this->find($employee);
        $row->update(['photo_file_id' => null]);

        return ApiResponse::ok($this->body($row->fresh()));
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>, 2: list<array<string, mixed>>}
     */
    private function validatedProfile(Request $request, ?HrEmployee $row): array
    {
        $company = app(TenantContext::class)->companyId();
        $in = fn (string $table) => Rule::exists($table, 'id')->where('company_id', $company);
        $request->merge(['card_no' => trim((string) $request->input('card_no')), 'nid' => preg_replace('/\s+/', '', (string) $request->input('nid')) ?: null]);
        $text = fn (int $max = 255) => ['nullable', 'string', 'max:'.$max];
        $d = $request->validate([
            'card_no' => ['required', 'string', 'max:40'], 'company_id' => ['nullable', Rule::in([$company])], 'factory_id' => ['required', $in('factories')],
            'first_name' => $text(80), 'last_name' => $text(80), 'name' => ['required', 'string', 'max:255'], 'nick_name' => $text(80),
            'father_name' => $text(), 'mother_name' => $text(), 'guardian_name' => $text(), 'guardian_relation' => ['nullable', Rule::in(self::RELATIONS)],
            'nid' => ['nullable', 'regex:/^(\d{10}|\d{13}|\d{17})$/'], 'birth_certificate_no' => $text(40), 'date_of_birth' => ['nullable', 'date', 'before:today'],
            'nationality' => ['required', 'string', 'max:64'], 'religion' => ['required', Rule::in(self::RELIGIONS)], 'gender' => ['required', Rule::in(self::GENDERS)],
            'marital_status' => ['nullable', Rule::in(self::MARITAL)], 'child_count' => ['nullable', 'integer', 'min:0', 'max:30'], 'blood_group' => ['nullable', Rule::in(self::BLOOD)],
            'mobile' => ['nullable', 'regex:/^01\d{9}$/'], 'home_phone' => $text(30), 'home_phone_ext' => $text(10), 'email' => ['nullable', 'email', 'max:255'], 'tin' => $text(30),
            'department_id' => ['nullable', $in('departments')], 'section_id' => ['nullable', $in('sections')], 'designation_id' => ['nullable', $in('designations')],
            'employment_type_id' => ['required', $in('hr_employment_types')], 'building_id' => ['nullable', $in('buildings')], 'floor_id' => ['nullable', $in('floors')],
            'line_id' => ['nullable', $in('production_lines')], 'grade_id' => ['nullable', $in('grades')], 'shift_id' => ['nullable', $in('hr_shifts')],
            'joining_date' => ['nullable', 'date'], 'gross_salary' => [$row ? 'nullable' : 'required', 'numeric', 'gt:0', 'max:99999999'],
            'status' => ['required', Rule::in(HrEmployee::STATUSES)], 'separation_date' => ['nullable', 'date'], 'active' => ['nullable', 'boolean'],
            'present_address' => $text(1000), 'present_post_code' => $text(12), 'permanent_address' => $text(1000), 'permanent_post_code' => $text(12),
            'emergency_name' => $text(), 'emergency_relation' => ['nullable', Rule::in(self::RELATIONS)], 'emergency_mobile' => ['nullable', 'regex:/^01\d{9}$/'], 'emergency_address' => $text(1000),
            'passport_no' => $text(30), 'passport_issue_place' => $text(80), 'passport_issue_date' => ['nullable', 'date'], 'passport_expire_date' => ['nullable', 'date'],
            'driving_license_no' => $text(30), 'driving_license_expire' => ['nullable', 'date'],
            'name_bn' => $text(), 'higher_education' => $text(120), 'spouse_name' => $text(), 'spouse_relation' => ['nullable', Rule::in(self::RELATIONS)], 'spouse_employee_code' => $text(24),
            'stay_with_family' => ['nullable', 'boolean'], 'festival_ot_eligible' => ['nullable', 'boolean'],
            'pay_lines' => ['nullable', 'array', 'max:60'], 'pay_lines.*.pay_element_id' => ['required', $in('hr_pay_elements')], 'pay_lines.*.type' => ['required', Rule::in(['ALLOWANCE', 'DEDUCTION'])],
            'pay_lines.*.core_salary' => ['nullable', 'boolean'], 'pay_lines.*.amount' => ['required', 'numeric', 'min:0', 'max:99999999'], 'pay_lines.*.active' => ['nullable', 'boolean'], 'pay_lines.*.effect_salary' => ['nullable', 'boolean'],
            'checks' => ['nullable', 'array', 'max:60'], 'checks.*.id' => ['nullable', 'string'], 'checks.*.check_type' => ['required', 'string', 'max:120'], 'checks.*.verified_by' => ['nullable', 'string', 'max:160'],
            'checks.*.verified_on' => ['nullable', 'date'], 'checks.*.cleared' => ['nullable', 'boolean'], 'checks.*.remarks' => ['nullable', 'string', 'max:400'],
        ], [
            'required' => __('hrpayroll.emp.prof.v.required'), 'card_no.required' => __('hrpayroll.emp.prof.v.card_required'),
            'nid.regex' => __('hrpayroll.emp.nid_digits'), 'mobile.regex' => __('hrpayroll.emp.mobile_format'), 'emergency_mobile.regex' => __('hrpayroll.emp.mobile_format'),
            'date_of_birth.before' => __('hrpayroll.emp.dob_past'), 'gross_salary.required' => __('hrpayroll.emp.prof.v.gross_required'), 'gross_salary.gt' => __('hrpayroll.emp.prof.v.gross_required'),
            'pay_lines.*.pay_element_id.required' => __('hrpayroll.emp.prof.v.line_element'), 'checks.*.check_type.required' => __('hrpayroll.emp.prof.v.check_type'),
        ], $this->attributes());

        // The card the terminal reads is one person's.
        $taken = HrEmployee::query()->where('card_no', $d['card_no'])->when($row, fn ($q) => $q->whereKeyNot($row->id))->value('employee_code');
        if ($taken) {
            throw ValidationException::withMessages(['card_no' => __('hrpayroll.emp.card_taken', ['card' => $d['card_no'], 'code' => $taken])]);
        }
        // A posting needs its department and designation, as Employee Assignment asks.
        if (collect(self::POSTING)->contains(fn ($k) => ! empty($d[$k]))) {
            foreach (['department_id' => 'department_required', 'designation_id' => 'designation_required'] as $k => $msg) {
                if (empty($d[$k])) {
                    throw ValidationException::withMessages([$k => __("hrpayroll.emp.{$msg}")]);
                }
            }
        }
        if (! empty($d['separation_date']) && ! empty($d['joining_date']) && $d['separation_date'] < $d['joining_date']) {
            throw ValidationException::withMessages(['separation_date' => __('hrpayroll.emp.prof.v.separation_before')]);
        }
        if (! empty($d['passport_issue_date']) && ! empty($d['passport_expire_date']) && $d['passport_expire_date'] <= $d['passport_issue_date']) {
            throw ValidationException::withMessages(['passport_expire_date' => __('hrpayroll.emp.prof.v.passport_dates')]);
        }

        // The salary structure follows the employee type: the same gazette where it has one, else the type's newest.
        $structures = collect(SalaryStructures::all());
        $structure = ($row ? $structures->first(fn ($s) => $s['gazette_id'] === $row->gazette_id && $s['employment_type_id'] === $d['employment_type_id']) : null)
            ?? $structures->firstWhere('employment_type_id', $d['employment_type_id'])
            ?? throw ValidationException::withMessages(['employment_type_id' => __('hrpayroll.emp.prof.v.no_structure')]);
        $gross = isset($d['gross_salary']) ? (float) $d['gross_salary'] : (float) $row->gross_a;
        if (SalaryStructures::split($structure['heads'], $gross)['basic'] <= 0) {
            throw ValidationException::withMessages(['gross_salary' => __('hrpayroll.emp.gross_too_low')]);
        }

        $lines = array_values($d['pay_lines'] ?? []);
        $seen = [];
        foreach ($lines as $i => $l) {
            if (isset($seen[$l['pay_element_id']])) {
                throw ValidationException::withMessages(["pay_lines.{$i}.pay_element_id" => __('hrpayroll.emp.prof.v.line_twice', ['n' => $i + 1])]);
            }
            $seen[$l['pay_element_id']] = true;
        }
        $checks = array_values($d['checks'] ?? []);

        $columns = array_diff_key($d, array_flip(['company_id', 'gross_salary', 'pay_lines', 'checks']));
        foreach (['active' => true, 'stay_with_family' => false, 'festival_ot_eligible' => false] as $k => $default) {
            $columns[$k] = (bool) ($d[$k] ?? $default);
        }
        foreach (self::POSTING as $k) {
            $columns[$k] = $d[$k] ?? null;
        }

        return [$columns + ['gazette_id' => $structure['gazette_id'], 'gross_a' => $gross], $lines, $checks];
    }

    /** @param list<array<string, mixed>> $lines Saving replaces the employee's lines with what is on the grid. */
    private function saveLines(HrEmployee $row, array $lines): void
    {
        DB::table('hr_employee_pay_lines')->where('employee_id', $row->id)->delete();
        $now = now();
        foreach ($lines as $i => $l) {
            DB::table('hr_employee_pay_lines')->insert([
                'id' => strtolower((string) Str::ulid()), 'company_id' => $row->company_id, 'employee_id' => $row->id, 'pay_element_id' => $l['pay_element_id'], 'type' => $l['type'],
                'core_salary' => (bool) ($l['core_salary'] ?? false), 'amount' => round((float) $l['amount'], 2), 'active' => (bool) ($l['active'] ?? true), 'effect_salary' => (bool) ($l['effect_salary'] ?? true),
                'sort_order' => $i, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    /** @param list<array<string, mixed>> $checks A check keeps the date it was filed; one left off the grid is removed. */
    private function saveChecks(HrEmployee $row, array $checks): void
    {
        $now = now();
        $kept = [];
        foreach ($checks as $i => $c) {
            $values = ['check_type' => trim($c['check_type']), 'verified_by' => $c['verified_by'] ?? null, 'cleared' => (bool) ($c['cleared'] ?? false), 'remarks' => $c['remarks'] ?? null, 'sort_order' => $i, 'updated_at' => $now];
            $id = $c['id'] ?? null;
            if ($id && DB::table('hr_employee_background_checks')->where('employee_id', $row->id)->where('id', $id)->update($values + (empty($c['verified_on']) ? [] : ['verified_on' => $c['verified_on']]))) {
                $kept[] = $id;

                continue;
            }
            $kept[] = $new = strtolower((string) Str::ulid());
            DB::table('hr_employee_background_checks')->insert($values + ['id' => $new, 'company_id' => $row->company_id, 'employee_id' => $row->id, 'verified_on' => $c['verified_on'] ?? now('Asia/Dhaka')->toDateString(), 'created_by' => $this->caller()->auditUserId(), 'created_at' => $now]);
        }
        DB::table('hr_employee_background_checks')->where('employee_id', $row->id)->whereNotIn('id', $kept)->delete();
    }

    /** @return array<string, mixed> The whole profile, with the names the print and the header show. */
    private function body(HrEmployee $e): array
    {
        $base = $this->rows(new Collection([$e]))[0];
        $types = HrEmploymentType::query()->pluck('name', 'id');
        $elements = HrPayElement::query()->get(['id', 'code', 'name'])->keyBy('id');
        $lines = DB::table('hr_employee_pay_lines')->where('employee_id', $e->id)->orderBy('sort_order')->get()->map(fn ($l) => [
            'pay_element_id' => $l->pay_element_id, 'element' => $elements[$l->pay_element_id]->name ?? '—', 'type' => $l->type, 'core_salary' => (bool) $l->core_salary,
            'amount' => (float) $l->amount, 'active' => (bool) $l->active, 'effect_salary' => (bool) $l->effect_salary,
        ])->all();
        $net = collect($lines)->filter(fn ($l) => $l['active'] && $l['effect_salary'])->sum(fn ($l) => $l['type'] === 'DEDUCTION' ? -$l['amount'] : $l['amount']);
        $file = $e->photo_file_id ? DB::table('file_attachments')->where('id', $e->photo_file_id)->first(['id', 'original_name']) : null;
        $profile = [];
        foreach (HrEmployee::PROFILE as $k) {
            $v = $e->{$k};
            $profile[$k] = $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v;
        }

        return $base + $profile + [
            'factory_id' => $e->factory_id, 'employment_type_id' => $e->employment_type_id, 'employee_type' => $types[$e->employment_type_id] ?? null, 'business_type' => $this->businessTypes()[$e->employment_type_id] ?? null,
            'company' => (string) DB::table('companies')->where('id', $e->company_id)->value('name'), 'office' => $e->factory_id ? Factory::query()->whereKey($e->factory_id)->value('name') : null,
            'gross_salary' => (float) $e->gross_a, 'pay_lines' => $lines, 'net_lines' => round((float) $net, 2),
            'checks' => DB::table('hr_employee_background_checks')->where('employee_id', $e->id)->orderBy('sort_order')->get()->map(fn ($c) => [
                'id' => $c->id, 'check_type' => $c->check_type, 'verified_by' => $c->verified_by, 'verified_on' => substr((string) $c->verified_on, 0, 10), 'cleared' => (bool) $c->cleared, 'remarks' => $c->remarks,
            ])->all(),
            'photo' => $file ? ['id' => $file->id, 'name' => $file->original_name] : null,
        ];
    }

    /** @return array<string, string> Each employee type's business type — the top of its tree (Staff, Worker). */
    private function businessTypes(): array
    {
        $types = HrEmploymentType::query()->get(['id', 'name', 'parent_id'])->keyBy('id');
        $root = function (string $id) use (&$root, $types): string {
            $t = $types[$id];

            return $t->parent_id && isset($types[$t->parent_id]) ? $root($t->parent_id) : $t->name;
        };

        return $types->map(fn ($t) => $root($t->id))->all();
    }

    /** @return array<string, string> */
    private function attributes(): array
    {
        $out = [];
        foreach (['card_no', 'factory_id', 'name', 'nationality', 'religion', 'gender', 'employment_type_id', 'status', 'gross_salary', 'department_id', 'designation_id'] as $k) {
            $out[$k] = __("hrpayroll.emp.prof.f.{$k}");
        }

        return $out;
    }
}
