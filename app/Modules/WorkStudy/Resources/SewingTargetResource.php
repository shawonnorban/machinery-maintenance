<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\WorkStudy\Models\WsSewingTarget;
use App\Modules\WorkStudy\Support\WsTargets;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Garment Target Setting → Records and New sewing target: an order a line
 * runs on a day, with the manpower, hours and efficiency its target is worked
 * out from — Target[Gen Hr] is (Used OP + ASO) × WH × 60 × T.Effi ÷ SMV,
 * never typed — and what it made. Records show how each stands: behind,
 * achieved or off. Work Study → Target & Feeding (AnnotechERP 14/03).
 */
class SewingTargetResource extends ResourceDefinition
{
    private const COUNTS = ['used_op', 'used_aso', 'layout_op', 'layout_aso', 'wip_rfi', 'prod_gen', 'prod_ot'];

    public function key(): string
    {
        return 'ws-sewing-targets';
    }

    public function model(): string
    {
        return WsSewingTarget::class;
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
        return ['target_date', 'floor_id', 'planning_line_id', 'buyer_id', 'order_style', 'used_op', 'used_aso', 'layout_op', 'layout_aso', 'smv', 't_effi', 'wh', 'wh_from', 'wh_to', 'wip_rfi', 'prod_gen', 'prod_ot', 'is_merged', 'is_single', 'is_off'];
    }

    public function booleans(): array
    {
        return ['is_merged', 'is_single', 'is_off'];
    }

    public function query(): Builder
    {
        return WsSewingTarget::query()->orderByDesc('target_date')->orderBy('sort_order')->orderBy('created_at');
    }

    public function messages(): array
    {
        return [
            'smv.required' => __('workstudy.cost.v.smv'), 'smv.gt' => __('workstudy.cost.v.smv'),
            'planning_line_id.required' => __('workstudy.tf.v.line'), 'floor_id.required' => __('workstudy.cost.v.floor'), 'target_date.required' => __('workstudy.cost.v.date'),
            'used_op.required' => __('workstudy.tf.v.used_op'), 'used_aso.required' => __('workstudy.tf.v.used_aso'),
            't_effi.required' => __('workstudy.tf.v.t_effi'), 'wh.required' => __('workstudy.tf.v.wh'), 'wh_to.gte' => __('workstudy.tf.v.wh_range'),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = app(TenantContext::class)->companyId();
        $count = ['nullable', 'integer', 'min:0', 'max:9999999'];

        return [
            'target_date' => [$required, 'date'],
            'floor_id' => [$required, Rule::exists('floors', 'id')->where('company_id', $company)],
            'planning_line_id' => [$required, Rule::exists('planning_lines', 'id')->where('company_id', $company)->where('floor_id', request('floor_id', $row?->getAttribute('floor_id')))],
            'buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'order_style' => ['nullable', 'string', 'max:64'],
            'used_op' => [$required, 'integer', 'min:0', 'max:999'],
            'used_aso' => [$required, 'integer', 'min:0', 'max:999'],
            'layout_op' => ['nullable', 'integer', 'min:0', 'max:999'],
            'layout_aso' => ['nullable', 'integer', 'min:0', 'max:999'],
            'smv' => [$required, 'numeric', 'gt:0', 'max:999'],
            't_effi' => [$required, 'numeric', 'min:0', 'max:100'],
            'wh' => [$required, 'numeric', 'min:0', 'max:24'],
            'wh_from' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'wh_to' => ['nullable', 'numeric', 'min:0', 'max:24', ...(request()->filled('wh_from') ? ['gte:wh_from'] : [])],
            'wip_rfi' => $count, 'prod_gen' => $count, 'prod_ot' => $count,
            'is_merged' => ['boolean'], 'is_single' => ['boolean'], 'is_off' => ['boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $value = fn (string $k) => $data[$k] ?? $row?->getAttribute($k);
        $data['target_gen'] = WsTargets::targetGen((int) $value('used_op'), (int) $value('used_aso'), (float) $value('wh'), (float) $value('t_effi'), (float) $value('smv'));
        if (! $row) {
            $data['sort_order'] = (int) WsSewingTarget::query()->where('planning_line_id', $data['planning_line_id'])->whereDate('target_date', $data['target_date'])->max('sort_order') + 1;
            $data['created_by'] = $this->actingUserId();
        }

        return $data;
    }

    public function present(Model $row): array
    {
        /** @var WsSewingTarget $row */
        $out = parent::present($row);
        $out['target_date'] = $row->target_date?->toDateString();
        $out['line'] = DB::table('planning_lines')->where('id', $row->planning_line_id)->value('code');
        $out['floor_code'] = DB::table('floors')->where('id', $row->floor_id)->value('code');
        $out['target_gen'] = (int) $row->target_gen;
        $out['target_ot'] = $row->target_ot;
        $out['ttl_target'] = (int) $row->target_gen + (int) $row->target_ot;
        $out['ttl_prod'] = (int) $row->prod_gen + (int) $row->prod_ot;
        $out['status'] = WsTargets::status($row);

        return $out;
    }
}
