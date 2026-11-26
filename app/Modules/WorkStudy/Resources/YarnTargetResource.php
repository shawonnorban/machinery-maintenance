<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\WorkStudy\Models\WsYarnTarget;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Excess Yarn Allowance → Records and New excess yarn target: the yarn
 * overrun (qty/kg) a department is allowed in a month, one line a department a
 * month. Work Study → Target & Feeding (AnnotechERP 14/03).
 */
class YarnTargetResource extends ResourceDefinition
{
    public function key(): string
    {
        return 'ws-yarn-targets';
    }

    public function model(): string
    {
        return WsYarnTarget::class;
    }

    public function viewPermission(): string
    {
        return 'workstudy.target.view';
    }

    public function managePermission(): string
    {
        return 'workstudy.target.manage';
    }

    public function columns(): array
    {
        return ['period', 'department_id', 'target_qty'];
    }

    public function query(): Builder
    {
        return WsYarnTarget::query()->orderByDesc('period')->orderBy('created_at');
    }

    public function messages(): array
    {
        return [
            'target_qty.required' => __('workstudy.tf.v.target'), 'year.required' => __('workstudy.ot.v.year'), 'month.required' => __('workstudy.ot.v.month'),
            'department_id.required' => __('workstudy.tf.v.department'),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = app(TenantContext::class)->companyId();
        $period = request()->filled(['year', 'month']) ? sprintf('%04d-%02d', (int) request('year'), (int) request('month')) : $row?->getAttribute('period');

        return [
            'year' => [$required, 'integer', 'min:2000', 'max:2100'],
            'month' => [$required, 'integer', 'min:1', 'max:12'],
            'department_id' => [$required, Rule::exists('departments', 'id')->where('company_id', $company), function (string $attribute, mixed $value, \Closure $fail) use ($period, $row): void {
                // One line a department a month.
                if ($period && WsYarnTarget::query()->where('period', $period)->where('department_id', $value)->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists()) {
                    $fail(__('workstudy.tf.v.yarn_taken'));
                }
            }],
            'target_qty' => [$required, 'numeric', 'min:0', 'max:99999999'],
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
        $out['month_name'] = Carbon::createFromFormat('Y-m-d', "{$period}-01")->format('F');
        $out['department'] = DB::table('departments')->where('id', $row->getAttribute('department_id'))->value('name');

        return $out;
    }
}
