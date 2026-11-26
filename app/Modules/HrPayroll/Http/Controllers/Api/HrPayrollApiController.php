<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Http\Controllers\Api;

use App\Modules\Accounting\Models\AccFiscalYear;
use App\Modules\Accounting\Support\AccountingDefaults;
use App\Modules\HrPayroll\Models\HrAllowance;
use App\Modules\HrPayroll\Models\HrBonusRuleSet;
use App\Modules\HrPayroll\Models\HrDeduction;
use App\Modules\HrPayroll\Models\HrDesignationGroup;
use App\Modules\HrPayroll\Models\HrEmploymentType;
use App\Modules\HrPayroll\Models\HrGazette;
use App\Modules\HrPayroll\Models\HrGazetteGradeWage;
use App\Modules\HrPayroll\Models\HrGazetteLine;
use App\Modules\HrPayroll\Models\HrGazettePolicy;
use App\Modules\HrPayroll\Models\HrHoliday;
use App\Modules\HrPayroll\Models\HrPayElement;
use App\Modules\HrPayroll\Models\HrPayPeriod;
use App\Modules\HrPayroll\Models\HrPayrollSetting;
use App\Modules\HrPayroll\Models\HrShiftTeam;
use App\Modules\HrPayroll\Resources\GazetteLineResource;
use App\Modules\HrPayroll\Resources\HrFiscalYearResource;
use App\Modules\HrPayroll\Support\HolidayCalendar;
use App\Modules\HrPayroll\Support\HrDefaults;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\User;
use App\Modules\Reporting\Imports\ImportReader;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Department;
use App\Modules\Tenancy\Models\Grade;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * HR & Payroll → Master Data (AnnotechERP 24/01): what its forms pick from;
 * the employment type tree; allowance and deduction entry, typed or read from
 * a file; a wage gazette with its policies and salary structure; the payroll
 * settings; moving a pay period through its life; and the fiscal year
 * register — each grid saved as a whole.
 */
class HrPayrollApiController extends ApiController
{
    public function lookups(): JsonResponse
    {
        $this->allow('hrpayroll.master.view');
        $company = $this->caller()->companyId;
        HrDefaults::ensure($company);
        AccountingDefaults::fiscalYears($company);
        $elements = HrPayElement::query()->where('active', true)->orderBy('code')->get(['id', 'code', 'name']);
        $companyName = Company::query()->whereKey($company)->value('name');
        $companyCode = Company::query()->whereKey($company)->value('code');
        $option = fn ($m) => ['value' => $m->id, 'label' => $m->name];

        return ApiResponse::ok([
            'company' => ['value' => $company, 'label' => $companyCode ? "{$companyName} ({$companyCode})" : $companyName],
            'types' => HrEmploymentType::query()->orderBy('level')->orderBy('name')->get(['id', 'name'])->map($option)->all(),
            'groups' => HrDesignationGroup::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])->map($option)->all(),
            'grades' => Grade::query()->orderBy('sort_order')->orderBy('code')->get(['id', 'name'])->map($option)->all(),
            'elements' => $elements->map(fn ($e) => ['value' => $e->id, 'label' => "{$e->name} ({$e->code})", 'code' => $e->code])->all(),
            'periods' => HrPayPeriod::query()->orderBy('period_no')->get(['id', 'period_no', 'closed'])->map(fn ($p) => ['value' => $p->id, 'label' => "{$p->period_no} — {$companyName}", 'no' => $p->period_no, 'closed' => (bool) $p->closed])->all(),
            'fiscal_years' => AccFiscalYear::query()->orderByDesc('fy_start')->get(['id', 'name', 'fy_start', 'fy_end'])->map(fn ($y) => ['value' => $y->id, 'label' => $y->name, 'start' => $y->fy_start?->format('Y-m-d'), 'end' => $y->fy_end?->format('Y-m-d')])->all(),
            'rule_sets' => HrBonusRuleSet::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->map($option)->all(),
            'shift_teams' => HrShiftTeam::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->map($option)->all(),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name'])->map($option)->all(),
            'employees' => $this->employees($company)->map(fn ($u) => ['value' => $u->id, 'label' => $u->employee_code ? "{$u->name} ({$u->employee_code})" : $u->name])->values()->all(),
            'gazettes' => HrGazette::query()->orderByDesc('effective_date')->orderBy('name')->get(['id', 'name', 'effective_date'])->map(fn ($g) => [
                'value' => $g->id, 'label' => $g->name, 'effective' => $g->effective_date?->format('Y-m-d'),
                'lines' => HrGazetteLine::query()->where('gazette_id', $g->id)->count(),
                'policies' => HrGazettePolicy::query()->where('gazette_id', $g->id)->count(),
            ])->all(),
        ]);
    }

    /** Employment Type → Employee Type Information: the tree, indented. */
    public function typeTree(): JsonResponse
    {
        $this->allow('hrpayroll.master.view');
        HrDefaults::ensure($this->caller()->companyId);
        $types = HrEmploymentType::query()->orderBy('name')->get(['id', 'name', 'parent_id', 'level', 'is_leaf']);
        $out = [];
        $walk = function (?string $parent, int $depth) use (&$walk, &$out, $types): void {
            foreach ($types->where('parent_id', $parent) as $t) {
                $out[] = ['key' => $t->id, 'label' => str_repeat('— ', $depth).$t->name, 'hint' => "Level {$t->level}".($t->is_leaf ? ' · leaf' : ''), 'count' => $types->where('parent_id', $t->id)->count()];
                $walk($t->id, $depth + 1);
            }
        };
        $walk(null, 0);

        return ApiResponse::ok($out);
    }

    public function typeChildren(string $type): JsonResponse
    {
        $this->allow('hrpayroll.master.view');
        HrEmploymentType::query()->findOrFail($type);

        return ApiResponse::ok(HrEmploymentType::query()->where('parent_id', $type)->orderBy('name')->get()
            ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'name_bn' => $t->name_bn, 'short_name' => $t->short_name, 'level' => $t->level, 'is_leaf' => $t->is_leaf, 'leaf' => __($t->is_leaf ? 'hrpayroll.master.yes' : 'hrpayroll.master.no'), 'active' => $t->active])->all());
    }

    /**
     * Employee Type Information: the types filed under a node, corrected in
     * the grid and saved as a whole. A type is never removed here.
     */
    public function saveTypeChildren(Request $request, string $type): JsonResponse
    {
        $this->allow('hrpayroll.master.manage');
        HrEmploymentType::query()->findOrFail($type);
        $rows = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.id' => ['required', 'string'],
            'rows.*.name' => ['required', 'string', 'max:255'],
            'rows.*.name_bn' => ['required', 'string', 'max:255'],
            'rows.*.short_name' => ['required', 'string', 'max:16'],
            'rows.*.active' => ['sometimes', 'boolean'],
        ], [
            'rows.*.name.required' => __('hrpayroll.master.type_name_required'),
            'rows.*.name_bn.required' => __('hrpayroll.master.type_name_bn_required'),
            'rows.*.short_name.required' => __('hrpayroll.master.short_name_required'),
        ])['rows'];
        $children = HrEmploymentType::query()->where('parent_id', $type)->get()->keyBy('id');
        $names = array_map(fn ($r) => strtoupper(trim($r['name'])), $rows);
        if (count($names) !== count(array_unique($names))) {
            throw ValidationException::withMessages(['rows' => __('hrpayroll.master.type_taken')]);
        }
        foreach ($rows as $i => $r) {
            if (! $children->has($r['id'])) {
                throw ValidationException::withMessages(["rows.{$i}.id" => __('hrpayroll.master.not_under_node')]);
            }
            if (HrEmploymentType::query()->whereRaw('upper(trim(name)) = ?', [$names[$i]])->whereNotIn('id', array_column($rows, 'id'))->exists()) {
                throw ValidationException::withMessages(["rows.{$i}.name" => __('hrpayroll.master.type_taken')]);
            }
        }
        DB::transaction(function () use ($rows, $children): void {
            foreach ($rows as $r) {
                $children[$r['id']]->update(['name' => trim($r['name']), 'name_bn' => trim($r['name_bn']), 'short_name' => trim($r['short_name']), 'active' => $r['active'] ?? true]);
            }
        });

        return $this->typeChildren($type);
    }

    /**
     * Allowance / Deduction entry for one pay period and pay element: one
     * employee typed in, or every row of a checked file. An allowance entered
     * again replaces the amount; a deduction head may be taken only once.
     */
    public function entries(Request $request, string $kind): JsonResponse
    {
        $this->allow('hrpayroll.master.manage');
        $company = $this->caller()->companyId;
        $model = $this->entryModel($kind);
        $data = $request->validate([
            'pay_period_id' => [$kind === 'allowances' ? 'required' : 'nullable', Rule::exists('hr_pay_periods', 'id')->where('company_id', $company)],
            'pay_element_id' => ['nullable', Rule::exists('hr_pay_elements', 'id')->where('company_id', $company)],
            'replace' => ['sometimes', 'boolean'],
            'rows' => ['required', 'array', 'min:1', 'max:'.ImportReader::MAX_ROWS],
            'rows.*.user_id' => ['nullable', 'string'],
            'rows.*.employee_code' => ['nullable', 'string', 'max:64'],
            'rows.*.pay_element' => ['nullable', 'string', 'max:40'],
            'rows.*.pay_period' => ['nullable', 'string', 'max:40'],
            'rows.*.amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
        ], [
            'pay_period_id.required' => __('hrpayroll.master.pay_period_required'),
            'rows.*.amount.required' => __('hrpayroll.master.amount_required'),
            'rows.*.amount.gt' => __('hrpayroll.master.amount_positive'),
        ]);
        // Typed in, an allowance replaces the amount; a file never overwrites what is entered.
        $replace = $kind === 'allowances' && $request->boolean('replace');
        [$checked, $resolved] = $this->checkLines($kind, array_values($data['rows']), $data['pay_period_id'] ?? null, $data['pay_element_id'] ?? null, $replace);
        $errors = [];
        foreach ($checked as $i => $row) {
            if ($row['error']) {
                $field = $row['field'] === 'pay_period' && count($checked) === 1 && ! ($data['rows'][$i]['pay_period'] ?? null) ? 'pay_period_id' : "rows.{$i}.{$row['field']}";
                $errors[$field] = count($checked) === 1 ? $row['error'] : __('hrpayroll.master.row_numbered', ['row' => $i + 1, 'message' => $row['error']]);
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        DB::transaction(function () use ($model, $resolved, $company): void {
            foreach ($resolved as [$period, $element, $user, $amount]) {
                $model::query()->updateOrCreate(
                    ['pay_period_id' => $period, 'pay_element_id' => $element, 'user_id' => $user],
                    ['company_id' => $company, 'amount' => $amount],
                );
            }
        });

        return ApiResponse::ok(['saved' => count($resolved)]);
    }

    /**
     * Excel Upload → Show File Data: reads a CSV, Excel or JSON file and
     * checks every line as Upload would — an allowance file carries Emp Code
     * and Amount for the chosen element and period; a deduction file Emp Code,
     * Ded Type Id, Pay Period Id and Amount, the chosen period standing in for
     * a blank one. Nothing is written.
     */
    public function parseUpload(Request $request, ImportReader $reader): JsonResponse
    {
        $this->allow('hrpayroll.master.manage');
        $company = $this->caller()->companyId;
        $data = $request->validate([
            'kind' => ['required', Rule::in(['allowances', 'deductions'])],
            'format' => ['required', Rule::in(['csv', 'xlsx', 'json'])],
            'file' => ['required', 'file', 'max:5120'],
            'pay_period_id' => ['nullable', Rule::exists('hr_pay_periods', 'id')->where('company_id', $company)],
            'pay_element_id' => ['nullable', Rule::exists('hr_pay_elements', 'id')->where('company_id', $company)],
        ], ['file.required' => __('hrpayroll.master.file_required')]);
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $format = $data['format'];
        $allowed = ['csv' => ['csv', 'txt'], 'xlsx' => ['xlsx'], 'json' => ['json']][$format];
        if (! in_array($extension, $allowed, true)) {
            throw ValidationException::withMessages(['file' => __('hrpayroll.master.file_format', ['format' => strtoupper($format)])]);
        }
        $items = [];
        if ($format === 'json') {
            $decoded = json_decode((string) file_get_contents($file->getRealPath()), true);
            if (! is_array($decoded) || ! array_is_list($decoded)) {
                throw ValidationException::withMessages(['file' => __('hrpayroll.master.file_unreadable')]);
            }
            foreach ($decoded as $i => $item) {
                $items[$i + 1] = is_array($item) ? array_change_key_case($item) : [];
            }
        } else {
            foreach ($reader->rows($file->getRealPath(), $extension) as $line => $item) {
                $items[$line] = $item;
            }
        }
        if (! $items) {
            throw ValidationException::withMessages(['file' => __('hrpayroll.master.file_empty')]);
        }
        $pick = fn (array $item, array $names) => collect($names)->map(fn ($n) => $item[$n] ?? null)->first(fn ($v) => $v !== null && $v !== '');
        $lines = [];
        foreach ($items as $item) {
            $lines[] = [
                'employee_code' => (string) $pick($item, ['emp code', 'emp_code', 'employee code', 'employee_code']),
                'pay_element' => $data['kind'] === 'deductions' ? (string) $pick($item, ['ded type id', 'ded_type_id', 'pay element', 'pay_element']) : null,
                'pay_period' => $data['kind'] === 'deductions' ? (string) $pick($item, ['pay period id', 'pay_period_id', 'pay period', 'pay_period']) : null,
                'amount' => $pick($item, ['amount']),
            ];
        }
        [$checked] = $this->checkLines($data['kind'], $lines, $data['pay_period_id'] ?? null, $data['kind'] === 'allowances' ? ($data['pay_element_id'] ?? null) : null, false, true);

        return ApiResponse::ok(array_map(fn (array $row, int $line) => ['row' => $line, ...array_diff_key($row, ['field' => true])], $checked, array_keys($items)));
    }

    private function entryModel(string $kind): string
    {
        return match ($kind) {
            'allowances' => HrAllowance::class,
            'deductions' => HrDeduction::class,
            default => abort(404),
        };
    }

    /**
     * Checks entry lines against the employee, head and period registers: the
     * employee resolves to somebody active in the company, the head and the
     * pay period resolve and the period is open, no line repeats another, and
     * nothing already entered is overwritten unless `$replace`.
     *
     * @return array{0: list<array>, 1: list<array{0: string, 1: string, 2: string, 3: float}>}
     */
    private function checkLines(string $kind, array $lines, ?string $period, ?string $element, bool $replace, bool $lenient = false): array
    {
        $model = $this->entryModel($kind);
        $employees = $this->employees($this->caller()->companyId);
        $byCode = $employees->filter(fn ($u) => $u->employee_code)->keyBy(fn ($u) => strtoupper(trim($u->employee_code)));
        $elements = HrPayElement::query()->get(['id', 'code', 'name']);
        $periods = HrPayPeriod::query()->get(['id', 'period_no', 'closed']);
        $find = fn (Collection $set, ?string $value, string $code) => $value === null || trim($value) === '' ? null : $set->first(fn ($x) => $x->id === trim($value) || strtoupper((string) $x->{$code}) === strtoupper(trim($value)));
        $seen = [];
        $checked = [];
        $resolved = [];
        foreach ($lines as $line) {
            $code = trim((string) ($line['employee_code'] ?? ''));
            $user = ($line['user_id'] ?? null) ? $employees->firstWhere('id', $line['user_id']) : ($code === '' ? null : $byCode->get(strtoupper($code)));
            $head = $element ? $elements->firstWhere('id', $element) : $find($elements, $line['pay_element'] ?? null, 'code');
            $term = ($line['pay_period'] ?? null) ? $find($periods, $line['pay_period'], 'period_no') : ($period ? $periods->firstWhere('id', $period) : null);
            $amount = is_numeric($line['amount'] ?? null) ? (float) $line['amount'] : null;
            $key = "{$term?->id}|{$head?->id}|{$user?->id}";
            $exists = $user && $head && $term && $model::query()->where('pay_period_id', $term->id)->where('pay_element_id', $head->id)->where('user_id', $user->id)->exists();
            [$field, $error] = match (true) {
                ! ($line['user_id'] ?? null) && $code === '' => ['employee_code', __('hrpayroll.master.row_no_code')],
                ! $user => [($line['user_id'] ?? null) ? 'user_id' : 'employee_code', __('hrpayroll.master.row_unknown')],
                ! $head => ['pay_element', ($line['pay_element'] ?? null) ? __('hrpayroll.master.row_bad_head') : __('hrpayroll.master.pay_element_required')],
                ! $term => ['pay_period', ($line['pay_period'] ?? null) ? __('hrpayroll.master.row_bad_period') : __('hrpayroll.master.pay_period_required')],
                (bool) $term->closed => ['pay_period', __('hrpayroll.master.period_closed')],
                isset($seen[$key]) => ['employee_code', __('hrpayroll.master.row_twice')],
                $exists && ! $replace => ['employee_code', __($kind === 'deductions' ? 'hrpayroll.master.row_deducted' : 'hrpayroll.master.row_entered')],
                $amount === null || $amount <= 0 => ['amount', __('hrpayroll.master.row_bad_amount')],
                default => [null, null],
            };
            $seen[$key] = true;
            $checked[] = ['employee_code' => $code ?: $user?->employee_code, 'employee' => $user?->name, 'pay_element' => $head?->code ?? ($line['pay_element'] ?? null), 'pay_element_name' => $head?->name, 'pay_period' => $term?->period_no ?? ($line['pay_period'] ?? null), 'amount' => $amount, 'field' => $field, 'error' => $error];
            if (! $error) {
                $resolved[] = [$term->id, $head->id, $user->id, $amount];
            }
        }

        return [$checked, $resolved];
    }

    /** Wage Gazette: a gazette, its policies and its salary structure per category. */
    public function gazette(string $gazette): JsonResponse
    {
        $this->allow('hrpayroll.master.view');
        $g = HrGazette::query()->findOrFail($gazette);

        return ApiResponse::ok($this->gazettePayload($g));
    }

    public function saveGazette(Request $request, ?string $gazette = null): JsonResponse
    {
        $this->allow('hrpayroll.master.manage');
        $company = $this->caller()->companyId;
        $row = $gazette ? HrGazette::query()->findOrFail($gazette) : null;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', function (string $a, mixed $v, \Closure $fail) use ($row): void {
                if (HrGazette::query()->whereRaw('upper(trim(name)) = ?', [strtoupper(trim((string) $v))])->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists()) {
                    $fail(__('hrpayroll.master.gazette_taken'));
                }
            }],
            'number' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:1970', 'max:2100'],
            'industry' => ['nullable', 'string', 'max:255'],
            'effective_date' => ['required', 'date'],
            'active' => ['sometimes', 'boolean'],
            'reference' => ['nullable', 'string', 'max:255'],
            'policies' => ['present', 'array', 'max:100'],
            'policies.*.code' => ['required', 'string', 'max:24'],
            'policies.*.name' => ['required', 'string', 'max:255'],
            'policies.*.active' => ['sometimes', 'boolean'],
        ], [
            'name.required' => __('hrpayroll.master.gazette_name_required'),
            'effective_date.required' => __('hrpayroll.master.gazette_date_required'),
            'policies.*.code.required' => __('hrpayroll.master.policy_code_required'),
            'policies.*.name.required' => __('hrpayroll.master.policy_name_required'),
        ]);
        $codes = array_map(fn ($p) => strtoupper(trim($p['code'])), $data['policies']);
        if (count($codes) !== count(array_unique($codes))) {
            throw ValidationException::withMessages(['policies' => __('hrpayroll.master.policy_code_twice')]);
        }
        $saved = DB::transaction(function () use ($data, $row, $company, $codes): HrGazette {
            $fields = ['name' => trim($data['name']), 'number' => $data['number'] ?? null, 'year' => $data['year'] ?? null, 'industry' => $data['industry'] ?? null, 'effective_date' => $data['effective_date'], 'active' => $data['active'] ?? true, 'reference' => $data['reference'] ?? null];
            $g = $row ? tap($row)->update($fields) : HrGazette::query()->create(['company_id' => $company, ...$fields]);
            HrGazettePolicy::query()->where('gazette_id', $g->id)->whereNotIn('code', $codes)->delete();
            foreach ($data['policies'] as $i => $p) {
                HrGazettePolicy::query()->updateOrCreate(['gazette_id' => $g->id, 'code' => $codes[$i]], ['company_id' => $company, 'name' => trim($p['name']), 'active' => $p['active'] ?? true]);
            }

            return $g;
        });

        return ApiResponse::ok($this->gazettePayload($saved->fresh()));
    }

    public function deleteGazette(string $gazette): JsonResponse
    {
        $this->allow('hrpayroll.master.manage');
        $g = HrGazette::query()->findOrFail($gazette);
        if (HrGazetteLine::query()->where('gazette_id', $g->id)->exists() || HrGazetteGradeWage::query()->where('gazette_id', $g->id)->exists()) {
            throw ValidationException::withMessages(['gazette' => __('hrpayroll.master.gazette_in_use')]);
        }
        $g->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    /** Payroll Settings: the six settings every salary screen is computed with. */
    public function settings(): JsonResponse
    {
        $this->allow('hrpayroll.master.view');
        HrDefaults::ensure($this->caller()->companyId);

        return ApiResponse::ok($this->settingRows());
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.master.manage');
        $values = $request->validate(['values' => ['required', 'array']])['values'];
        $errors = [];
        foreach (HrDefaults::SETTINGS as $key => [, $rule]) {
            if (! array_key_exists($key, $values)) {
                continue;
            }
            $value = strtoupper(trim((string) $values[$key]));
            [$type, $spec] = explode(':', $rule, 2);
            $ok = $type === 'int'
                ? (ctype_digit($value) && (int) $value >= (int) explode(':', $spec)[0] && (int) $value <= (int) explode(':', $spec)[1])
                : in_array($value, explode(',', $spec), true);
            if (! $ok) {
                $errors["values.{$key}"] = __('hrpayroll.master.setting_invalid', ['setting' => __("hrpayroll.master.ps.settings.{$key}.label"), 'allowed' => str_replace([':', ','], [' – ', ' · '], $spec)]);
            }
            $values[$key] = $value;
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        foreach (HrDefaults::SETTINGS as $key => $spec) {
            if (array_key_exists($key, $values)) {
                $setting = HrPayrollSetting::query()->where('key', $key)->first();
                if ($setting && $setting->value !== $values[$key]) {
                    $setting->update(['value' => $values[$key]]);
                }
            }
        }

        return ApiResponse::ok($this->settingRows());
    }

    /** Pay Period Actions: the company, and how many pay periods it has. */
    public function periodCompanies(): JsonResponse
    {
        $this->allow('hrpayroll.master.view');
        $company = Company::query()->find($this->caller()->companyId, ['id', 'name']);

        return ApiResponse::ok([['key' => $company->id, 'label' => $company->name, 'count' => HrPayPeriod::query()->count()]]);
    }

    public function periodsOf(string $company): JsonResponse
    {
        $this->allow('hrpayroll.master.view');
        abort_unless($company === $this->caller()->companyId, 404);

        return ApiResponse::ok($this->periodRows());
    }

    /** What each move takes a period from, and to. */
    private const MOVES = [
        'SUBMIT' => [['INITIATED', 'SUBMITTED']],
        'ACTIVATE' => [['SUBMITTED', 'ACTIVATED']],
        'SEND_BACK' => [['SUBMITTED', 'INITIATED'], ['ACTIVATED', 'SUBMITTED']],
        'CLOSE' => [['ACTIVATED', 'CLOSED']],
        'REOPEN' => [['CLOSED', 'ACTIVATED']],
    ];

    /**
     * Pay Period Actions: each period's staged move — Submit for Activation,
     * Activate, Send back, Close, Re-open — and its Show for Report and
     * Remarks. Only the moves its current status allows succeed; the rest are
     * refused with the reason. The totals are salary processing's, never typed.
     */
    public function savePeriodsOf(Request $request, string $company): JsonResponse
    {
        $this->allow('hrpayroll.master.manage');
        abort_unless($company === $this->caller()->companyId, 404);
        $rows = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.id' => ['required', 'string'],
            'rows.*.action' => ['nullable', Rule::in(array_keys(self::MOVES))],
            'rows.*.show_for_report' => ['sometimes', 'boolean'],
            'rows.*.remarks' => ['nullable', 'string', 'max:1000'],
        ])['rows'];
        $periods = HrPayPeriod::query()->whereIn('id', array_column($rows, 'id'))->get()->keyBy('id');
        $errors = [];
        $targets = [];
        foreach ($rows as $i => $r) {
            $period = $periods->get($r['id']);
            if (! $period) {
                $errors["rows.{$i}.id"] = __('hrpayroll.master.period_unknown');

                continue;
            }
            if ($r['action'] ?? null) {
                $to = collect(self::MOVES[$r['action']])->first(fn ($m) => $m[0] === $period->status)[1] ?? null;
                if (! $to) {
                    $errors["rows.{$i}.action"] = __('hrpayroll.master.period_move', ['period' => $period->period_no, 'move' => __("hrpayroll.master.opt.move.{$r['action']}"), 'status' => __("hrpayroll.master.opt.status.{$period->status}")]);

                    continue;
                }
                $targets[$period->id] = $to;
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        DB::transaction(function () use ($rows, $periods, $targets): void {
            foreach ($rows as $r) {
                $period = $periods->get($r['id']);
                $to = $targets[$period->id] ?? $period->status;
                $period->update([
                    'status' => $to,
                    'closed' => $to === 'CLOSED',
                    'show_for_report' => $r['show_for_report'] ?? $period->show_for_report,
                    'remarks' => array_key_exists('remarks', $r) ? $r['remarks'] : $period->remarks,
                ]);
            }
        });

        return ApiResponse::ok($this->periodRows());
    }

    /** New Fiscal Year: Finance & Ledger's register, opened from Payroll Settings. */
    public function fiscalRegisters(): JsonResponse
    {
        $this->allow('hrpayroll.master.view');
        AccountingDefaults::fiscalYears($this->caller()->companyId);

        return ApiResponse::ok([['key' => 'FY', 'label' => __('hrpayroll.master.ps.fy_register'), 'count' => AccFiscalYear::query()->count()]]);
    }

    public function fiscalYearsOf(string $register): JsonResponse
    {
        $this->allow('hrpayroll.master.view');
        abort_unless($register === 'FY', 404);

        return ApiResponse::ok($this->fiscalRows());
    }

    /**
     * Adds the new years, corrects the old and deletes the ones left out of
     * the grid — a year with pay periods or holidays filed under it is kept.
     */
    public function saveFiscalYearsOf(Request $request, string $register, HrFiscalYearResource $definition): JsonResponse
    {
        $this->allow('hrpayroll.master.manage');
        abort_unless($register === 'FY', 404);
        $rows = $request->validate(['rows' => ['present', 'array', 'max:50']])['rows'];
        DB::transaction(function () use ($rows, $definition): void {
            $kept = array_filter(array_column($rows, 'id'));
            foreach (AccFiscalYear::query()->whereNotIn('id', $kept)->get() as $gone) {
                if (HrPayPeriod::query()->where('fiscal_year_id', $gone->id)->exists() || DB::table('hr_holidays')->where('fiscal_year_id', $gone->id)->exists()) {
                    throw ValidationException::withMessages(['rows' => __('hrpayroll.master.fy_in_use', ['year' => $gone->name])]);
                }
                $gone->delete();
            }
            foreach ($rows as $i => $r) {
                $year = ($r['id'] ?? null) ? AccFiscalYear::query()->findOrFail($r['id']) : null;
                $input = array_intersect_key($r, array_flip(['name', 'fy_start', 'fy_end', 'cal_start', 'cal_end']));
                $input['closed'] = ! filter_var($r['active'] ?? true, FILTER_VALIDATE_BOOL);
                $validator = Validator::make($input, $definition->rules($year), $definition->messages());
                if ($validator->fails()) {
                    throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($m, $k) => ["rows.{$i}.{$k}" => __('hrpayroll.master.row_error', ['row' => $i + 1, 'message' => $m[0]])])->all());
                }
                try {
                    $data = $definition->beforeSave($validator->validated(), $year);
                } catch (ValidationException $e) {
                    throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($m, $k) => ["rows.{$i}.{$k}" => __('hrpayroll.master.row_error', ['row' => $i + 1, 'message' => $m[0]])])->all());
                }
                $year ? $year->update($data) : AccFiscalYear::query()->create(['company_id' => $this->caller()->companyId, ...$data]);
            }
        });

        return ApiResponse::ok($this->fiscalRows());
    }

    /** Festival Calendar → Holidays: put a dated holiday's days on the factory calendar. */
    public function holidayToCalendar(string $holiday): JsonResponse
    {
        $this->allow('hrpayroll.master.manage');
        $row = HrHoliday::query()->findOrFail($holiday);
        if (! $row->start_date) {
            throw ValidationException::withMessages(['start_date' => __('hrpayroll.master.holiday_no_date')]);
        }
        if (! $row->active) {
            throw ValidationException::withMessages(['active' => __('hrpayroll.master.holiday_inactive')]);
        }
        HolidayCalendar::file($row);

        return ApiResponse::ok(['calendar_days' => $row->fresh()->calendar_days]);
    }

    /** The company's people, who allowances and deductions name until the employee master arrives. */
    private function employees(string $company): Collection
    {
        $members = CompanyUser::query()->where('company_id', $company)->where('status', 'ACTIVE')->pluck('user_id');

        return User::query()->whereIn('id', $members)->orderBy('name')->get(['id', 'name', 'employee_code']);
    }

    private function gazettePayload(HrGazette $g): array
    {
        $lines = HrGazetteLine::query()->where('gazette_id', $g->id)->get();
        $codes = HrPayElement::query()->pluck('code', 'id');
        $types = HrEmploymentType::query()->pluck('name', 'id');
        $structure = $lines->groupBy('employment_type_id')->map(function (Collection $set, string $type) use ($codes, $types): array {
            $by = $set->mapWithKeys(fn ($l) => [$codes[$l->pay_element_id] => (float) $l->amount]);
            $means = fn (string $code) => $by->has($code) ? GazetteLineResource::means($code, $by[$code]) : '—';
            $gross = $by->get('GROSS');
            $basic = ($gross && $by->get('BASIC')) ? round(($gross - $by->get('MED', 0) - $by->get('FOOD', 0) - $by->get('CONV', 0)) / $by['BASIC'], 2) : null;

            return [
                'category' => $types[$type] ?? '', 'basic' => $means('BASIC'), 'house_rent' => $means('HRENT'), 'medical' => $means('MED'), 'food' => $means('FOOD'),
                'transport' => $means('CONV'), 'other' => $means('SHIFT'), 'gross_min' => $gross ? '৳'.number_format($gross) : null, 'basic_at_min' => $basic ? '৳'.number_format($basic, 2) : null,
            ];
        })->sortByDesc('category')->values()->all();

        return [
            'id' => $g->id, 'name' => $g->name, 'number' => $g->number, 'year' => $g->year, 'industry' => $g->industry,
            'effective_date' => $g->effective_date?->format('Y-m-d'), 'active' => $g->active, 'reference' => $g->reference,
            'policies' => HrGazettePolicy::query()->where('gazette_id', $g->id)->orderBy('code')->get(['code', 'name', 'active'])->map(fn ($p) => ['code' => $p->code, 'name' => $p->name, 'active' => $p->active])->all(),
            'structure' => $structure,
            'lines' => $lines->count(),
        ];
    }

    private function settingRows(): array
    {
        $stored = HrPayrollSetting::query()->get()->keyBy('key');

        return array_map(fn (string $key) => [
            'key' => $key,
            'value' => $stored->get($key)?->value ?? HrDefaults::SETTINGS[$key][0],
            'allowed' => str_replace([':', ','], [' – ', ' · '], explode(':', HrDefaults::SETTINGS[$key][1], 2)[1]),
            'updated_at' => $stored->get($key)?->updated_at?->format('Y-m-d'),
        ], array_keys(HrDefaults::SETTINGS));
    }

    private function periodRows(): array
    {
        $years = AccFiscalYear::query()->pluck('name', 'id');
        $elements = HrPayElement::query()->pluck('name', 'id');

        return HrPayPeriod::query()->orderByDesc('pay_date')->orderByDesc('period_no')->get()->map(function ($p) use ($years, $elements) {
            $allowances = HrAllowance::query()->where('pay_period_id', $p->id);

            return [
                'id' => $p->id, 'period_no' => $p->period_no, 'fiscal_year' => $years[$p->fiscal_year_id] ?? null, 'month' => (string) $p->month, 'period_type' => $p->period_type,
                'pay_element' => $elements[$p->pay_element_id] ?? null, 'pay_date' => $p->pay_date?->format('Y-m-d'), 'show_for_report' => $p->show_for_report, 'closed' => $p->closed,
                'total_pays' => (clone $allowances)->distinct()->count('user_id'), 'total_payable' => round((float) $allowances->sum('amount') - (float) HrDeduction::query()->where('pay_period_id', $p->id)->sum('amount'), 2),
                'closed_label' => $p->closed ? 'YES' : 'NO', 'remarks' => $p->remarks, 'status' => $p->status, 'status_label' => __("hrpayroll.master.opt.status_long.{$p->status}"), 'action' => '',
            ];
        })->all();
    }

    private function fiscalRows(): array
    {
        return AccFiscalYear::query()->orderByDesc('fy_start')->get()
            ->map(fn ($y) => ['id' => $y->id, 'name' => $y->name, 'fy_start' => $y->fy_start?->format('Y-m-d'), 'fy_end' => $y->fy_end?->format('Y-m-d'), 'cal_start' => $y->cal_start?->format('Y-m-d'), 'cal_end' => $y->cal_end?->format('Y-m-d'), 'pay_periods' => HrPayPeriod::query()->where('fiscal_year_id', $y->id)->count(), 'active' => ! $y->closed, 'status' => ! $y->closed])->all();
    }
}
