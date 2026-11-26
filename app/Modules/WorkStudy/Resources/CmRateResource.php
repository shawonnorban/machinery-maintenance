<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\WorkStudy\Models\WsCmRate;
use App\Modules\WorkStudy\Models\WsSpec;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Spec-Wise CM Rate → Records and New CM rate: one CM per item specification
 * per booking month, with the order quantity, budget expense, lead time and
 * dates it was quoted on. The GMT Item List reads it for each order's booking
 * month. Work Study → Costing (AnnotechERP 14/05).
 */
class CmRateResource extends ResourceDefinition
{
    public function key(): string
    {
        return 'ws-cm-rates';
    }

    public function model(): string
    {
        return WsCmRate::class;
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
        return ['spec_id', 'buyer_id', 'booking_month', 'order_qty', 'cm_per_dozen', 'budget_exp_pct', 'lead_time_days', 'confirm_date', 'ship_date'];
    }

    public function query(): Builder
    {
        return WsCmRate::query()->orderByDesc('booking_month')->orderByDesc('created_at');
    }

    public function messages(): array
    {
        return [
            'spec_id.required' => __('workstudy.cost.v.spec'),
            'booking_month.required' => __('workstudy.cost.v.month'),
            'booking_month.regex' => __('workstudy.cost.v.month_format'),
            'order_qty.required' => __('workstudy.cost.v.order_qty'),
            'order_qty.min' => __('workstudy.cost.v.order_qty'),
            'cm_per_dozen.required' => __('workstudy.cost.v.cm'),
            'cm_per_dozen.gt' => __('workstudy.cost.v.cm'),
            'spec_id.unique' => __('workstudy.cost.v.cm_taken'),
            'ship_date.after_or_equal' => __('workstudy.cost.v.ship_after'),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = app(TenantContext::class)->companyId();
        $month = request()->input('booking_month', $row?->getAttribute('booking_month'));

        return [
            'spec_id' => [$required, Rule::exists('ws_specs', 'id')->where('company_id', $company),
                Rule::unique('ws_cm_rates', 'spec_id')->where('company_id', $company)->where('booking_month', $month)->ignore($row?->getKey())],
            'buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'booking_month' => [$required, 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'order_qty' => [$required, 'integer', 'min:1', 'max:99999999'],
            'cm_per_dozen' => [$required, 'numeric', 'gt:0', 'max:99999999'],
            'budget_exp_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:999'],
            'confirm_date' => ['nullable', 'date'],
            'ship_date' => ['nullable', 'date', ...(request()->filled('confirm_date') ? ['after_or_equal:confirm_date'] : [])],
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
        $out = parent::present($row);
        $spec = WsSpec::query()->find($row->getAttribute('spec_id'));
        $out['spec'] = $spec ? "{$spec->style_no}/{$spec->variation_no}" : null;
        $out['buyer'] = Buyer::query()->find($row->getAttribute('buyer_id'))?->short_name;

        return $out;
    }
}
