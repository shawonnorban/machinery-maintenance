<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\WorkStudy\Models\WsChangeover;
use App\Modules\WorkStudy\Models\WsSewingTarget;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Style Feeding & Change-over → Records and New style change-over: a line
 * moving from one style to the next — the minutes the change-over cost (SCO)
 * and the feeding time (FT) — and, once finalised, what the efficiency report
 * deducts. Feeding starts the first day the new order is on the line's target
 * sheet and ends the first day it produced. A finalised change-over is not
 * removed. Work Study → Target & Feeding (AnnotechERP 14/03).
 */
class ChangeoverResource extends ResourceDefinition
{
    public function key(): string
    {
        return 'ws-changeovers';
    }

    public function model(): string
    {
        return WsChangeover::class;
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
        return ['floor_id', 'planning_line_id', 'changeover_date', 'new_buyer_id', 'new_order', 'new_gmt_item', 'prev_buyer_id', 'prev_order', 'prev_gmt_item', 'prev_output_on', 'sco_minutes', 'ft_hours', 'remarks', 'finalized'];
    }

    public function booleans(): array
    {
        return ['finalized'];
    }

    public function query(): Builder
    {
        return WsChangeover::query()->orderByDesc('changeover_date')->orderBy('created_at');
    }

    public function messages(): array
    {
        return [
            'planning_line_id.required' => __('workstudy.tf.v.line'), 'floor_id.required' => __('workstudy.cost.v.floor'), 'changeover_date.required' => __('workstudy.cost.v.date'),
            'new_order.required' => __('workstudy.tf.v.new_order'), 'sco_minutes.required' => __('workstudy.tf.v.sco'),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = app(TenantContext::class)->companyId();

        return [
            'floor_id' => [$required, Rule::exists('floors', 'id')->where('company_id', $company)],
            'planning_line_id' => [$required, Rule::exists('planning_lines', 'id')->where('company_id', $company)->where('floor_id', request('floor_id', $row?->getAttribute('floor_id')))],
            'changeover_date' => [$required, 'date'],
            'new_buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'new_order' => [$required, 'string', 'max:64'],
            'new_gmt_item' => ['nullable', 'string', 'max:120'],
            'prev_buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'prev_order' => ['nullable', 'string', 'max:64', 'different:new_order'],
            'prev_gmt_item' => ['nullable', 'string', 'max:120'],
            'prev_output_on' => ['nullable', 'date', 'before_or_equal:changeover_date'],
            'sco_minutes' => [$required, 'integer', 'min:1', 'max:9999'],
            'ft_hours' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'finalized' => ['boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (! $row) {
            $data['created_by'] = $this->actingUserId();
        }

        return $data;
    }

    public function beforeDelete(Model $row): void
    {
        if ($row->getAttribute('finalized')) {
            throw ValidationException::withMessages(['id' => __('workstudy.tf.v.finalized')]);
        }
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        foreach (['changeover_date', 'prev_output_on'] as $d) {
            $out[$d] = $row->getAttribute($d)?->toDateString();
        }
        $out['line'] = DB::table('planning_lines')->where('id', $row->getAttribute('planning_line_id'))->value('code');
        $out['floor'] = DB::table('floors')->where('id', $row->getAttribute('floor_id'))->value('name');
        $buyers = Buyer::query()->whereIn('id', array_filter([$row->getAttribute('new_buyer_id'), $row->getAttribute('prev_buyer_id')]))->pluck('short_name', 'id');
        $out['new_buyer'] = $buyers[$row->getAttribute('new_buyer_id')] ?? null;
        $out['prev_buyer'] = $buyers[$row->getAttribute('prev_buyer_id')] ?? null;
        $fed = WsSewingTarget::query()->where('planning_line_id', $row->getAttribute('planning_line_id'))->where('order_style', $row->getAttribute('new_order'))
            ->whereDate('target_date', '>=', $row->getAttribute('changeover_date'))->orderBy('target_date')->get();
        $out['feeding_start'] = $fed->first()?->target_date?->toDateString();
        $out['feeding_end'] = $fed->first(fn (WsSewingTarget $t) => (int) $t->prod_gen + (int) $t->prod_ot > 0)?->target_date?->toDateString();
        $out['status'] = $row->getAttribute('finalized') ? 'FINALIZED' : 'OPEN';

        return $out;
    }
}
