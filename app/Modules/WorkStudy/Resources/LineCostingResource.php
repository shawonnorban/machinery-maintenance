<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\WorkStudy\Models\WsLineCosting;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Daily Line Cost Sheet → Records and New line costing row: an order on a
 * floor costed for one day — its SMV, FOB and per-piece cutting, sewing and
 * finishing rates. One row per order per floor per day. Work Study → Costing
 * (AnnotechERP 14/05).
 */
class LineCostingResource extends ResourceDefinition
{
    public function key(): string
    {
        return 'ws-line-costings';
    }

    public function model(): string
    {
        return WsLineCosting::class;
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
        return ['floor_id', 'cost_date', 'buyer_id', 'style_no', 'order_no', 'gmt_item', 'bgt_as_cm_pct', 'exc_rate', 'smv', 'fob', 'rate_cut', 'rate_sew', 'rate_fin'];
    }

    public function query(): Builder
    {
        return WsLineCosting::query()->orderByDesc('cost_date')->orderBy('order_no');
    }

    public function messages(): array
    {
        return [
            'floor_id.required' => __('workstudy.cost.v.floor'),
            'cost_date.required' => __('workstudy.cost.v.date'),
            'style_no.required' => __('workstudy.cost.v.style'),
            'order_no.required' => __('workstudy.cost.v.order'),
            'order_no.unique' => __('workstudy.cost.v.order_taken'),
            'smv.required' => __('workstudy.cost.v.smv'),
            'smv.gt' => __('workstudy.cost.v.smv'),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = app(TenantContext::class)->companyId();
        $money = ['nullable', 'numeric', 'min:0', 'max:99999999'];

        return [
            'floor_id' => [$required, Rule::exists('floors', 'id')->where('company_id', $company)],
            'cost_date' => [$required, 'date'],
            'buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'style_no' => [$required, 'string', 'max:64'],
            'order_no' => [$required, 'string', 'max:64', Rule::unique('ws_line_costings', 'order_no')->where('company_id', $company)
                ->where('floor_id', request('floor_id', $row?->getAttribute('floor_id')))->where('cost_date', request('cost_date', $row?->getAttribute('cost_date')?->toDateString()))->ignore($row?->getKey())],
            'gmt_item' => ['nullable', 'string', 'max:120'],
            'bgt_as_cm_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'exc_rate' => $money,
            'smv' => [$required, 'numeric', 'gt:0', 'max:999'],
            'fob' => $money, 'rate_cut' => $money, 'rate_sew' => $money, 'rate_fin' => $money,
        ];
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['cost_date'] = $row->getAttribute('cost_date')?->toDateString();
        $floor = DB::table('floors')->where('id', $row->getAttribute('floor_id'))->first(['name', 'code']);
        $out['floor'] = $floor?->name;
        $out['floor_code'] = $floor?->code;
        $out['buyer'] = Buyer::query()->find($row->getAttribute('buyer_id'))?->short_name;

        return $out;
    }
}
