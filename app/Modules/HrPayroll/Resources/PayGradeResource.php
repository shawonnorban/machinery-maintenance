<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Resources;

use App\Modules\Tenancy\Models\Designation;
use App\Modules\Tenancy\Models\Grade;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Pay Grade: the grades employees and salary structures are on — Grade# in
 * English and Bengali numerals, and how many designations sit on each. The
 * same register as Company Management → Designation & Grade, so a grade is
 * kept once. HR & Payroll → Master Data (AnnotechERP 24/01).
 */
class PayGradeResource extends HrResource
{
    public function key(): string
    {
        return 'hr-grades';
    }

    public function model(): string
    {
        return Grade::class;
    }

    public function columns(): array
    {
        return ['code', 'name', 'code_bn', 'sort_order'];
    }

    public function booleans(): array
    {
        return ['active'];
    }

    public function query(): Builder
    {
        $this->ensureDefaults();

        return Grade::query()->orderBy('sort_order')->orderBy('code');
    }

    public function messages(): array
    {
        return ['code.required' => __('hrpayroll.master.grade_required')];
    }

    public function rules(?Model $row): array
    {
        // A list's defaults go in before the first row is checked against them.
        $this->ensureDefaults();
        $required = $row ? 'sometimes' : 'required';

        return [
            'code' => [$required, 'string', 'max:32', $this->uniqueText(Grade::class, 'code', $row, __('hrpayroll.master.grade_taken'))],
            'code_bn' => [$required, 'string', 'max:32'],
            'name' => ['nullable', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (array_key_exists('active', $data)) {
            $data['status'] = $data['active'] ? 'ACTIVE' : 'INACTIVE';
            unset($data['active']);
        }
        if (array_key_exists('name', $data) || ! $row) {
            $code = $data['code'] ?? $row?->getAttribute('code');
            $data['name'] = trim((string) ($data['name'] ?? '')) !== '' ? trim($data['name']) : "Grade {$code}";
        }
        if (! $row) {
            $data['status'] ??= 'ACTIVE';
            $data['sort_order'] = is_numeric($data['code']) ? (int) $data['code'] : ((int) Grade::query()->max('sort_order')) + 1;
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['active'] = $row->getAttribute('status') === 'ACTIVE';
        $out['designations'] = Designation::query()->where('grade_id', $row->getKey())->count();

        return $out;
    }
}
