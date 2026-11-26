<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\WorkStudy\Models\WsOhCost;
use App\Modules\WorkStudy\Models\WsOhHead;
use App\Modules\WorkStudy\Models\WsOhSection;
use App\Modules\WorkStudy\Support\WsCosting;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Overhead Cost Allocation → Records and New cost head: a cost head's month —
 * what it cost and its working days. Where the money goes is typed on the
 * Monthly Cost Re-arrange sheet, a whole month at a time; the record shows
 * how much has been re-arranged and what it costs a day. One line per head per
 * month. Work Study → Costing (AnnotechERP 14/05).
 */
class OhCostResource extends ResourceDefinition
{
    public function key(): string
    {
        return 'ws-oh-costs';
    }

    public function model(): string
    {
        return WsOhCost::class;
    }

    public function viewPermission(): string
    {
        return 'workstudy.costing.view';
    }

    public function managePermission(): string
    {
        return 'workstudy.costing.manage';
    }

    public function columns(): array
    {
        return ['period', 'head_id', 'monthly_expense', 'work_days'];
    }

    public function query(): Builder
    {
        WsCosting::ensure(app(TenantContext::class)->companyId());

        return WsOhCost::query()->orderByDesc('period')->orderBy('created_at');
    }

    public function messages(): array
    {
        return [
            'year.required' => __('workstudy.cost.v.year'),
            'month.required' => __('workstudy.cost.v.month_pick'),
            'head_id.required' => __('workstudy.cost.v.head'),
            'head_id.unique' => __('workstudy.cost.v.head_taken'),
            'monthly_expense.required' => __('workstudy.cost.v.expense'),
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
            'head_id' => [$required, Rule::exists('ws_oh_heads', 'id')->where('company_id', $company),
                Rule::unique('ws_oh_costs', 'head_id')->where('company_id', $company)->where('period', $period)->ignore($row?->getKey())],
            'monthly_expense' => [$required, 'numeric', 'min:0', 'max:999999999999'],
            'work_days' => ['nullable', 'integer', 'min:1', 'max:31'],
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
        $head = WsOhHead::query()->find($row->getAttribute('head_id'));
        $period = (string) $row->getAttribute('period');
        $out['company'] = DB::table('companies')->where('id', $row->getAttribute('company_id'))->value('name');
        $out['year'] = (int) substr($period, 0, 4);
        $out['month'] = (int) substr($period, 5, 2);
        $out['period_label'] = Carbon::createFromFormat('Y-m-d', "{$period}-01")->format('F Y');
        $out['section'] = $head ? WsOhSection::query()->find($head->section_id)?->name : null;
        $out['particular'] = $head?->name;
        $rearranged = array_sum(array_map(fn ($c) => (float) $row->getAttribute($c), WsCosting::COLUMNS));
        $out['rearranged'] = $rearranged > 0 ? round($rearranged, 2) : null;
        $days = (int) $row->getAttribute('work_days');
        $out['per_day'] = $days > 0 ? round((float) $row->getAttribute('monthly_expense') / $days, 2) : null;

        return $out;
    }
}
