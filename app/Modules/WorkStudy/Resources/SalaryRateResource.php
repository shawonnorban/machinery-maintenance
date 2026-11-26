<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\WorkStudy\Models\WsSalaryRate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Monthly Salary & OT Rates → Records and New salary and OT rate: a month's
 * average operator and ASO salary and overtime rate — all four together, one
 * line a month. The line costing values a day and an overtime hour at them.
 * Work Study → OT & Salary (AnnotechERP 14/06).
 */
class SalaryRateResource extends ResourceDefinition
{
    private const RATES = ['op_salary', 'aso_salary', 'op_ot_rate', 'aso_ot_rate'];

    public function key(): string
    {
        return 'ws-salary-rates';
    }

    public function model(): string
    {
        return WsSalaryRate::class;
    }

    public function viewPermission(): string
    {
        return 'workstudy.ot.view';
    }

    public function managePermission(): string
    {
        return 'workstudy.ot.manage';
    }

    public function columns(): array
    {
        return ['period', ...self::RATES];
    }

    public function query(): Builder
    {
        return WsSalaryRate::query()->orderByDesc('period');
    }

    public function messages(): array
    {
        $all = __('workstudy.ot.v.rates');

        return [
            'year.required' => __('workstudy.ot.v.year'), 'month.required' => __('workstudy.ot.v.month'),
            'op_salary.required' => $all, 'aso_salary.required' => $all, 'op_ot_rate.required' => $all, 'aso_ot_rate.required' => $all,
            'op_salary.gt' => $all, 'aso_salary.gt' => $all, 'op_ot_rate.gt' => $all, 'aso_ot_rate.gt' => $all,
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $period = request()->filled(['year', 'month']) ? sprintf('%04d-%02d', (int) request('year'), (int) request('month')) : $row?->getAttribute('period');
        $rate = [$required, 'numeric', 'gt:0', 'max:99999999'];

        return [
            'year' => [$required, 'integer', 'min:2000', 'max:2100'],
            'month' => [$required, 'integer', 'min:1', 'max:12', function (string $attribute, mixed $value, \Closure $fail) use ($period, $row): void {
                // One line a month.
                if ($period && WsSalaryRate::query()->where('period', $period)->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists()) {
                    $fail(__('workstudy.ot.v.month_taken'));
                }
            }],
            'op_salary' => $rate, 'aso_salary' => $rate, 'op_ot_rate' => $rate, 'aso_ot_rate' => $rate,
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['year'], $data['month'])) {
            $data['period'] = sprintf('%04d-%02d', (int) $data['year'], (int) $data['month']);
        }
        unset($data['year'], $data['month']);

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $period = (string) $row->getAttribute('period');
        $out['company'] = DB::table('companies')->where('id', $row->getAttribute('company_id'))->value('name');
        $out['year'] = (int) substr($period, 0, 4);
        $out['month'] = (int) substr($period, 5, 2);
        $out['period_label'] = Carbon::createFromFormat('Y-m-d', "{$period}-01")->format('F Y');

        return $out;
    }
}
