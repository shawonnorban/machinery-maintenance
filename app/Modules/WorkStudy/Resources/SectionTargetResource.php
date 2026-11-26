<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\WorkStudy\Models\WsSectionTarget;
use App\Modules\WorkStudy\Support\WsSectionProfit;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Section Profit & Loss Setup → Records and New income target: what one
 * section is set to produce a day in a month, the rate it is worth and the
 * working days. Target Income is daily target × rate × working days, derived
 * on save and never typed. One target a section a month. Work Study →
 * Section Profitability (AnnotechERP 14/08).
 */
class SectionTargetResource extends ResourceDefinition
{
    public function key(): string
    {
        return 'ws-section-targets';
    }

    public function model(): string
    {
        return WsSectionTarget::class;
    }

    public function viewPermission(): string
    {
        return 'workstudy.profit.view';
    }

    public function managePermission(): string
    {
        return 'workstudy.profit.manage';
    }

    public function columns(): array
    {
        return ['period', 'section_id', 'sub_section', 'daily_target', 'daily_target_inhouse', 'daily_target_subcon', 'avg_rate', 'work_days', 'oh_calculated'];
    }

    public function query(): Builder
    {
        return WsSectionTarget::query()->orderByDesc('period')->orderBy('created_at');
    }

    public function messages(): array
    {
        $both = __('workstudy.sp.v.target_rate');

        return [
            'daily_target.required' => $both, 'daily_target.gt' => $both, 'avg_rate.required' => $both, 'avg_rate.gt' => $both,
            'company.required' => __('workstudy.sp.v.company'), 'year.required' => __('workstudy.ot.v.year'), 'month.required' => __('workstudy.ot.v.month'),
            'section_id.required' => __('workstudy.sp.v.section'), 'work_days.required' => __('workstudy.sp.v.work_days'),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = app(TenantContext::class)->companyId();
        $period = request()->filled(['year', 'month']) ? sprintf('%04d-%02d', (int) request('year'), (int) request('month')) : $row?->getAttribute('period');
        $number = ['nullable', 'numeric', 'min:0', 'max:9999999999'];

        return [
            'company' => [$required, Rule::in([$company])],
            'year' => [$required, 'integer', 'min:2000', 'max:2100'],
            'month' => [$required, 'integer', 'min:1', 'max:12'],
            'section_id' => [$required, Rule::exists('sections', 'id')->where('company_id', $company), function (string $attribute, mixed $value, \Closure $fail) use ($period, $row): void {
                // One target a section a month.
                if ($period && WsSectionTarget::query()->where('period', $period)->where('section_id', $value)->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists()) {
                    $fail(__('workstudy.sp.v.target_taken'));
                }
            }],
            'sub_section' => ['nullable', 'string', 'max:120'],
            'daily_target' => [$required, 'numeric', 'gt:0', 'max:9999999999'],
            'daily_target_inhouse' => $number,
            'daily_target_subcon' => $number,
            'avg_rate' => [$required, 'numeric', 'gt:0', 'max:9999999999'],
            'work_days' => [$required, 'integer', 'min:1', 'max:31'],
            'oh_calculated' => $number,
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['year'], $data['month'])) {
            $data['period'] = sprintf('%04d-%02d', (int) $data['year'], (int) $data['month']);
        }
        unset($data['year'], $data['month'], $data['company']);
        $value = fn (string $k) => array_key_exists($k, $data) ? $data[$k] : $row?->getAttribute($k);
        $data['target_income'] = WsSectionProfit::income((float) $value('daily_target'), (float) $value('avg_rate'), (int) $value('work_days'));
        if (! $row && blank($data['sub_section'] ?? null)) {
            $data['sub_section'] = WsSectionProfit::defaultSubSection((string) DB::table('sections')->where('id', $data['section_id'])->value('name'));
        }

        return $data;
    }

    public function present(Model $row): array
    {
        /** @var WsSectionTarget $row */
        $out = parent::present($row);
        $period = (string) $row->period;
        $out['company'] = $row->company_id;
        $out['year'] = (int) substr($period, 0, 4);
        $out['month'] = (int) substr($period, 5, 2);
        $out['period_label'] = Carbon::createFromFormat('Y-m-d', "{$period}-01")->format('F Y');
        $out['section'] = DB::table('sections')->where('id', $row->section_id)->value('name');
        $out['target_income'] = (float) $row->target_income;

        return $out;
    }
}
