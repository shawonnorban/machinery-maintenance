<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\WorkStudy\Models\WsManMinuteAdjustment;
use App\Modules\WorkStudy\Support\WsFloorOps;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Standard Minute Adjustment → Records and New man-minute adjustment: the
 * general-shift operators and ASOs a line booked away from its order for one
 * reason on a day. The overtime hours behind them are filled in on the sheet;
 * Man OT is added up from them and never typed. One row a line a day a
 * reason. Work Study → Floor Operations (AnnotechERP 14/07).
 */
class ManMinuteAdjustmentResource extends ResourceDefinition
{
    public function key(): string
    {
        return 'ws-man-minute-adjustments';
    }

    public function model(): string
    {
        return WsManMinuteAdjustment::class;
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
        return ['adjust_date', 'floor_id', 'planning_line_id', 'reason', 'used_op', 'used_aso'];
    }

    public function query(): Builder
    {
        return WsManMinuteAdjustment::query()->orderByDesc('adjust_date')->orderBy('created_at');
    }

    public function messages(): array
    {
        return [
            'adjust_date.required' => __('workstudy.fo.v.date'), 'floor_id.required' => __('workstudy.fo.v.floor'), 'planning_line_id.required' => __('workstudy.fo.v.line'),
            'reason.required' => __('workstudy.fo.v.mm_reason'), 'used_op.required' => __('workstudy.fo.v.op'), 'used_aso.required' => __('workstudy.fo.v.aso'),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = app(TenantContext::class)->companyId();
        $date = request('adjust_date', $row?->getAttribute('adjust_date')?->toDateString());
        $line = request('planning_line_id', $row?->getAttribute('planning_line_id'));
        $ot = (int) $row?->getAttribute('ot_op') + (int) $row?->getAttribute('ot_aso');

        return [
            'adjust_date' => [$required, 'date'],
            'floor_id' => [$required, Rule::exists('floors', 'id')->where('company_id', $company)],
            'planning_line_id' => [$required, Rule::exists('planning_lines', 'id')->where('company_id', $company)->where('floor_id', request('floor_id', $row?->getAttribute('floor_id')))],
            'reason' => [$required, Rule::in(WsFloorOps::MM_REASONS), function (string $attribute, mixed $value, \Closure $fail) use ($date, $line, $row): void {
                // One row a line a day a reason: the sheet shows each reason once.
                if ($date && $line && WsManMinuteAdjustment::query()->where('planning_line_id', $line)->whereDate('adjust_date', $date)->where('reason', $value)->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists()) {
                    $fail(__('workstudy.fo.v.mm_taken'));
                }
            }],
            'used_op' => [$required, 'integer', 'min:0', 'max:9999'],
            'used_aso' => [$required, 'integer', 'min:0', 'max:9999', function (string $attribute, mixed $value, \Closure $fail) use ($row, $ot): void {
                // A reason with nobody booked away is no adjustment.
                if ((int) request('used_op', $row?->getAttribute('used_op')) + (int) $value + $ot === 0) {
                    $fail(__('workstudy.fo.v.mm_nobody'));
                }
            }],
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
        /** @var WsManMinuteAdjustment $row */
        $out = parent::present($row);
        $out['adjust_date'] = $row->adjust_date?->toDateString();
        $out['line'] = DB::table('planning_lines')->where('id', $row->planning_line_id)->value('code');
        $out['floor'] = DB::table('floors')->where('id', $row->floor_id)->value('name');
        $out['ot_op'] = (int) $row->ot_op;
        $out['ot_aso'] = (int) $row->ot_aso;

        return $out;
    }
}
