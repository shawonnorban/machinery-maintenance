<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Resources;

use App\Modules\Accounting\Models\AccFiscalYear;
use App\Modules\Accounting\Support\AccountingDefaults;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Fiscal Year Setup: the accounting years the company keeps, the calendar year
 * each is registered against, and whether it is closed — a closed year takes
 * no further postings. Years do not overlap. Finance & Ledger → Master Data
 * (AnnotechERP 23/01).
 */
class FiscalYearResource extends AccountingResource
{
    public function key(): string
    {
        return 'acc-fiscal-years';
    }

    /** Brickland removes a row here; one still in use is refused (deactivate it instead). */
    public function deletable(): bool
    {
        return true;
    }

    public function model(): string
    {
        return AccFiscalYear::class;
    }

    public function columns(): array
    {
        return ['name', 'fy_start', 'fy_end', 'cal_start', 'cal_end', 'closed', 'remarks'];
    }

    public function booleans(): array
    {
        return ['closed'];
    }

    public function query(): Builder
    {
        AccountingDefaults::fiscalYears($this->company());

        return AccFiscalYear::query()->orderByDesc('fy_start');
    }

    public function messages(): array
    {
        return ['fy_start.required' => __('accounting.master.fy_start_required')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'fy_start' => [$required, 'date'],
            'name' => [$required, 'string', 'max:32', $this->uniqueText(AccFiscalYear::class, 'name', $row, __('accounting.master.fy_taken'))],
            'fy_end' => [$required, 'date'],
            'cal_start' => [$required, 'date'],
            'cal_end' => [$required, 'date'],
            'closed' => ['sometimes', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $date = fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : substr((string) $v, 0, 10);
        $p = array_map($date, array_merge($row ? $row->only(['fy_start', 'fy_end', 'cal_start', 'cal_end']) : [], array_intersect_key($data, array_flip(['fy_start', 'fy_end', 'cal_start', 'cal_end']))));
        if ($p['fy_end'] <= $p['fy_start']) {
            throw ValidationException::withMessages(['fy_end' => __('accounting.master.fy_order')]);
        }
        if ($p['cal_end'] <= $p['cal_start']) {
            throw ValidationException::withMessages(['cal_end' => __('accounting.master.cal_order')]);
        }
        $overlap = AccFiscalYear::query()->where('fy_start', '<=', $p['fy_end'])->where('fy_end', '>=', $p['fy_start'])->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['fy_start' => __('accounting.master.fy_overlap')]);
        }
        if (! $row) {
            $data['created_by'] = $this->actingUserId();
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['company'] = Company::query()->whereKey($row->getAttribute('company_id'))->value('name');
        $out['created_by'] = $row->getAttribute('created_by') ? User::query()->whereKey($row->getAttribute('created_by'))->value('name') : null;

        return $out;
    }
}
