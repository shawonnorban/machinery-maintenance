<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\WorkStudy\Models\WsOutputAdjustment;
use App\Modules\WorkStudy\Support\WsFloorOps;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Sewing Output Adjustment → Records and New production adjustment: a line's
 * output for one hour of a day corrected, the recorded figure kept beside the
 * adjusted one (an adjustment that erased what it replaced would not be
 * auditable). One correction a line an hour; a correction to the recorded
 * figure itself corrects nothing. Work Study → Floor Operations (AnnotechERP 14/07).
 */
class OutputAdjustmentResource extends ResourceDefinition
{
    public function key(): string
    {
        return 'ws-output-adjustments';
    }

    public function model(): string
    {
        return WsOutputAdjustment::class;
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
        return ['adjust_date', 'floor_id', 'planning_line_id', 'buyer_id', 'order_no', 'item_name', 'hour', 'recorded_qty', 'adjusted_qty', 'reason'];
    }

    public function query(): Builder
    {
        return WsOutputAdjustment::query()->orderByDesc('adjust_date')->orderBy('hour')->orderBy('created_at');
    }

    public function messages(): array
    {
        return [
            'adjust_date.required' => __('workstudy.fo.v.date'), 'floor_id.required' => __('workstudy.fo.v.floor'), 'hour.required' => __('workstudy.fo.v.hour'),
            'recorded_qty.required' => __('workstudy.fo.v.recorded'), 'adjusted_qty.required' => __('workstudy.fo.v.adjusted'), 'reason.required' => __('workstudy.fo.v.reason'),
            'adjusted_qty.different' => __('workstudy.fo.v.unchanged'),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = app(TenantContext::class)->companyId();
        $date = request('adjust_date', $row?->getAttribute('adjust_date')?->toDateString());
        $hour = request('hour', $row?->getAttribute('hour'));

        return [
            'adjust_date' => [$required, 'date'],
            'floor_id' => [$required, Rule::exists('floors', 'id')->where('company_id', $company)],
            'planning_line_id' => ['nullable', Rule::exists('planning_lines', 'id')->where('company_id', $company)->where('floor_id', request('floor_id', $row?->getAttribute('floor_id'))),
                function (string $attribute, mixed $value, \Closure $fail) use ($date, $hour, $row): void {
                    // One correction a line an hour.
                    if ($value && $date && $hour && WsOutputAdjustment::query()->where('planning_line_id', $value)->whereDate('adjust_date', $date)->where('hour', (int) $hour)->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists()) {
                        $fail(__('workstudy.fo.v.output_taken'));
                    }
                }],
            'buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'order_no' => ['nullable', 'string', 'max:64'],
            'item_name' => ['nullable', 'string', 'max:120'],
            'hour' => [$required, 'integer', Rule::in(WsFloorOps::HOURS)],
            'recorded_qty' => [$required, 'integer', 'min:0', 'max:9999999'],
            'adjusted_qty' => [$required, 'integer', 'min:0', 'max:9999999', ...(request()->has('recorded_qty') ? ['different:recorded_qty'] : [])],
            'reason' => [$required, 'string', 'max:2000'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (! $row) {
            $data['created_by'] = $this->actingUserId();
        }

        return $data;
    }

    public function present(Model $row): array
    {
        /** @var WsOutputAdjustment $row */
        $out = parent::present($row);
        $out['adjust_date'] = $row->adjust_date?->toDateString();
        $out['line'] = $row->planning_line_id ? DB::table('planning_lines')->where('id', $row->planning_line_id)->value('code') : null;
        $out['floor'] = DB::table('floors')->where('id', $row->floor_id)->value('name');
        $out['buyer'] = $row->buyer_id ? Buyer::query()->whereKey($row->buyer_id)->value('short_name') : null;
        $out['status'] = WsFloorOps::direction((int) $row->recorded_qty, (int) $row->adjusted_qty);

        return $out;
    }
}
