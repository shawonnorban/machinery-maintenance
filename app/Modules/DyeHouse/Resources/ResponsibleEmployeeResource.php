<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Resources;

use App\Modules\DyeHouse\Models\DyeResponsibleEmployee;
use App\Modules\DyeHouse\Support\DyeHouseDefaults;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Finishing & Quality Owner Setup: the people answerable for dyeing finishing
 * and for grey fabric quality, by role, and the four-to-six-digit pass code
 * each confirms their work with. The code is kept only as a keyed hash —
 * never shown, never sent back — and no two people share one. Until HR &
 * Payroll brings employees, the person is one of the company's users.
 * Dye House → Master Data (AnnotechERP 17/01).
 */
class ResponsibleEmployeeResource extends DyeMasterResource
{
    public function key(): string
    {
        return 'dye-responsible-employees';
    }

    public function model(): string
    {
        return DyeResponsibleEmployee::class;
    }

    public function columns(): array
    {
        return ['employee_type', 'user_id', 'employee_name', 'department', 'designation'];
    }

    public function query(): Builder
    {
        return DyeResponsibleEmployee::query()->orderBy('employee_type')->orderBy('employee_name');
    }

    public function messages(): array
    {
        return ['employee_type.required' => __('dyehouse.master.employee_type_required'), 'pass_code.regex' => __('dyehouse.master.pass_code_digits')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = $this->company();
        $type = request()->input('employee_type', $row?->getAttribute('employee_type'));
        $members = CompanyUser::query()->where('company_id', $company)->where('status', 'ACTIVE')->pluck('user_id')->all();

        return [
            'employee_type' => [$required, Rule::in(DyeResponsibleEmployee::TYPES)],
            'user_id' => [$required, Rule::in($members), Rule::unique('dye_responsible_employees', 'user_id')->where('company_id', $company)->where('employee_type', $type)->ignore($row?->getKey())],
            'employee_name' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            // Required on a new person; on an edit, left blank keeps the code they have.
            'pass_code' => [$row ? 'nullable' : 'required', 'regex:/^\d{4,6}$/', function (string $attribute, mixed $value, Closure $fail) use ($company, $row): void {
                $taken = DyeResponsibleEmployee::query()->where('pass_code_hash', DyeHouseDefaults::passCodeHash($company, (string) $value))->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
                if ($taken) {
                    $fail(__('dyehouse.master.pass_code_taken'));
                }
            }],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (! empty($data['pass_code'])) {
            $data['pass_code_hash'] = DyeHouseDefaults::passCodeHash($this->company(), (string) $data['pass_code']);
        }
        unset($data['pass_code']);
        if (! empty($data['user_id']) && empty($data['employee_name'])) {
            $data['employee_name'] = User::query()->whereKey($data['user_id'])->value('name');
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['user'] = User::query()->whereKey($row->getAttribute('user_id'))->value('name');

        return $out;
    }
}
