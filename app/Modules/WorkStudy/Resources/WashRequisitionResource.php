<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\WorkStudy\Models\WsWashRequisition;
use App\Modules\WorkStudy\Support\WsFloorOps;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Garment Wash Requisition → Records and New wash requisition: garments sent
 * out for wash against a style — how many, how fast the line makes them and
 * when the wash runs — numbered on save (GMT-WR-2026100001) and set to the
 * step Take Action names. Work Study → Floor Operations (AnnotechERP 14/07).
 */
class WashRequisitionResource extends ResourceDefinition
{
    public function key(): string
    {
        return 'ws-wash-requisitions';
    }

    public function model(): string
    {
        return WsWashRequisition::class;
    }

    public function viewPermission(): string
    {
        return 'workstudy.floorops.view';
    }

    public function managePermission(): string
    {
        return 'workstudy.floorops.manage';
    }

    public function columns(): array
    {
        return ['requisition_no', 'requisition_date', 'requested_by', 'attn_to', 'floor_id', 'planning_line_id', 'buyer_id', 'style_no', 'order_no', 'shipment_date', 'order_qty', 'wash_qty',
            'df_process', 'gmt_category', 'hourly_production', 'daily_production', 'start_date', 'close_date', 'remarks', 'status'];
    }

    public function query(): Builder
    {
        return WsWashRequisition::query()->orderByDesc('requisition_date')->orderByDesc('requisition_no');
    }

    public function messages(): array
    {
        return [
            'requisition_date.required' => __('workstudy.fo.v.req_date'), 'floor_id.required' => __('workstudy.fo.v.floor'), 'style_no.required' => __('workstudy.fo.v.style'),
            'order_no.required' => __('workstudy.fo.v.order'), 'wash_qty.required' => __('workstudy.fo.v.wash_qty'), 'wash_qty.min' => __('workstudy.fo.v.wash_qty'),
            'daily_production.required' => __('workstudy.fo.v.daily'), 'status.required' => __('workstudy.fo.v.action'), 'close_date.after_or_equal' => __('workstudy.fo.v.close_date'),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = app(TenantContext::class)->companyId();
        $count = ['nullable', 'integer', 'min:0', 'max:99999999'];

        return [
            'requisition_date' => [$required, 'date'],
            'requested_by' => ['nullable', Rule::exists('hr_employees', 'id')->where('company_id', $company)],
            'attn_to' => ['nullable', Rule::exists('company_users', 'user_id')->where('company_id', $company)],
            'floor_id' => [$required, Rule::exists('floors', 'id')->where('company_id', $company)],
            'planning_line_id' => ['nullable', Rule::exists('planning_lines', 'id')->where('company_id', $company)->where('floor_id', request('floor_id', $row?->getAttribute('floor_id')))],
            'buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'style_no' => [$required, 'string', 'max:64'],
            'order_no' => [$required, 'string', 'max:64'],
            'shipment_date' => ['nullable', 'date'],
            'order_qty' => $count,
            'wash_qty' => [$required, 'integer', 'min:1', 'max:99999999'],
            'df_process' => ['nullable', 'string', 'max:120'],
            'gmt_category' => ['nullable', 'string', 'max:120'],
            'hourly_production' => $count,
            'daily_production' => [$required, 'integer', 'min:0', 'max:99999999'],
            'start_date' => ['nullable', 'date'],
            'close_date' => ['nullable', 'date', ...(request()->filled('start_date') ? ['after_or_equal:start_date'] : [])],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'status' => [$required, Rule::in(WsFloorOps::WASH_STATUSES)],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        unset($data['requisition_no']);
        if (! $row) {
            $data['requisition_no'] = WsFloorOps::nextRequisitionNo((string) $data['requisition_date']);
            $data['created_by'] = $this->actingUserId();
        }

        return $data;
    }

    public function present(Model $row): array
    {
        /** @var WsWashRequisition $row */
        $out = parent::present($row);
        foreach (['requisition_date', 'shipment_date', 'start_date', 'close_date'] as $d) {
            $out[$d] = $row->getAttribute($d)?->toDateString();
        }
        $out['buyer'] = $row->buyer_id ? Buyer::query()->whereKey($row->buyer_id)->value('short_name') : null;
        $out['floor'] = DB::table('floors')->where('id', $row->floor_id)->value('name');
        $out['line'] = $row->planning_line_id ? DB::table('planning_lines')->where('id', $row->planning_line_id)->value('code') : null;
        $out['requested_by_name'] = $row->requested_by ? DB::table('hr_employees')->where('id', $row->requested_by)->value('name') : null;
        $out['attn_to_name'] = $row->attn_to ? DB::table('users')->where('id', $row->attn_to)->value('name') : null;

        return $out;
    }
}
