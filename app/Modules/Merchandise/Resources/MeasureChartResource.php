<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\Merchandise\Models\BuyerOffice;
use App\Modules\Merchandise\Models\MeasureChart;
use App\Modules\Merchandise\Models\MeasurePoint;
use App\Modules\Merchandise\Models\Style;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Lab Dip & Sample → Collar & Cuff Measurement Sheet → Records, New
 * measurement chart and Edit (AnnotechERP 06/10): a chart for a style, its
 * sub-style, the item (collar, cuff) and model. The buyer account follows
 * the style when none is picked. Its points of measure come from the Add
 * Measurement panel.
 */
class MeasureChartResource extends ResourceDefinition
{
    public function key(): string
    {
        return 'merch-measure-charts';
    }

    public function model(): string
    {
        return MeasureChart::class;
    }

    public function viewPermission(): string
    {
        return 'merchandise.sample.view';
    }

    public function managePermission(): string
    {
        return 'merchandise.sample.manage';
    }

    public function columns(): array
    {
        return ['style_id', 'buyer_office_id', 'sub_style', 'item_name', 'model_no', 'status'];
    }

    public function defaults(): array
    {
        return ['status' => 'DRAFT'];
    }

    public function query(): Builder
    {
        return MeasureChart::query()->latest();
    }

    public function deletable(): bool
    {
        return true;
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = app(TenantContext::class)->companyId();

        return [
            'style_id' => [$required, Rule::exists('merch_styles', 'id')->where('company_id', $company)],
            'buyer_office_id' => ['nullable', Rule::exists('buyer_offices', 'id')->where('company_id', $company)],
            'sub_style' => ['nullable', 'string', 'max:64'],
            'item_name' => [$required, 'string', 'max:96'],
            'model_no' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', Rule::in(MeasureChart::STATUSES)],
        ];
    }

    public function messages(): array
    {
        return [
            'style_id.required' => __('merchandise.measure.style_required'),
            'item_name.required' => __('merchandise.measure.item_required'),
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        foreach (['sub_style', 'item_name', 'model_no'] as $text) {
            if (array_key_exists($text, $data)) {
                $data[$text] = is_string($data[$text]) && trim($data[$text]) !== '' ? trim($data[$text]) : null;
            }
        }
        $style = $data['style_id'] ?? $row?->getAttribute('style_id');
        if ($style && empty($data['buyer_office_id']) && ! $row?->getAttribute('buyer_office_id')) {
            $data['buyer_office_id'] = Style::query()->whereKey($style)->value('buyer_office_id');
        }
        if ($row === null) {
            $data['created_by'] = $this->actingUserId();
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $style = Style::query()->whereKey($row->getAttribute('style_id'))->first();
        $out['style_no'] = $style?->styleNo();
        $out['style_name'] = $style?->description;
        $out['label'] = trim(($out['style_no'] ?? '—').' · '.$row->getAttribute('item_name'));
        $out['buyer_ac'] = $row->getAttribute('buyer_office_id') ? BuyerOffice::query()->whereKey($row->getAttribute('buyer_office_id'))->value('name') : null;
        $out['points'] = MeasurePoint::query()->where('chart_id', $row->getKey())->count();

        return $out;
    }
}
