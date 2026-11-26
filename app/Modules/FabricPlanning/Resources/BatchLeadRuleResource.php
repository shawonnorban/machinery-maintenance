<?php

declare(strict_types=1);

namespace App\Modules\FabricPlanning\Resources;

use App\Modules\FabricPlanning\Models\FpBatchLeadRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Batch Lead Days Before PCD: how many days before the shipment — or before
 * the PCD — a dye batch is planned, for a colour type, finish and wash, an
 * order lead time band and an order volume band. A shipment-based rule needs
 * its days before shipment, a PCD-based one its days before PCD. Fabric
 * Requirement → Master Data (AnnotechERP 13/01).
 */
class BatchLeadRuleResource extends FabricPlanningResource
{
    public function key(): string
    {
        return 'fp-batch-lead-rules';
    }

    public function model(): string
    {
        return FpBatchLeadRule::class;
    }

    public function columns(): array
    {
        return ['colour_type', 'finish_type', 'wash_type', 'lead_from', 'lead_to', 'volume_from', 'volume_to', 'production_lead_time', 'basis', 'days_before_shipment', 'days_before_pcd', 'is_embellishment'];
    }

    public function booleans(): array
    {
        return ['is_embellishment', 'all_colours', 'any_finish', 'any_wash'];
    }

    protected function anyBoxes(): array
    {
        return ['colour_type' => 'all_colours', 'finish_type' => 'any_finish', 'wash_type' => 'any_wash'];
    }

    public function query(): Builder
    {
        return FpBatchLeadRule::query()->orderBy('lead_from')->orderBy('volume_from');
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'colour_type' => ['nullable', Rule::in(FpBatchLeadRule::COLOUR_TYPES)],
            'all_colours' => ['sometimes', 'boolean'],
            'finish_type' => ['nullable', Rule::in(FpBatchLeadRule::FINISH_TYPES)],
            'any_finish' => ['sometimes', 'boolean'],
            'wash_type' => ['nullable', Rule::in(FpBatchLeadRule::WASH_TYPES)],
            'any_wash' => ['sometimes', 'boolean'],
            'lead_from' => [$required, 'integer', 'min:0', 'max:999'],
            'lead_to' => [$required, 'integer', 'gte:lead_from', 'max:999'],
            'volume_from' => [$required, 'integer', 'min:0', 'max:99999999'],
            'volume_to' => [$required, 'integer', 'gte:volume_from', 'max:99999999'],
            'production_lead_time' => [$required, 'integer', 'min:0', 'max:999'],
            'basis' => [$required, Rule::in(FpBatchLeadRule::BASES)],
            'days_before_shipment' => ['nullable', 'integer', 'min:0', 'max:999'],
            'days_before_pcd' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_embellishment' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $data = $this->applyAny($data, $row);
        $r = array_merge($row?->only(['basis', 'days_before_shipment', 'days_before_pcd']) ?? [], $data);
        if ($r['basis'] === 'SHIPMENT' && (int) ($r['days_before_shipment'] ?? 0) <= 0) {
            throw ValidationException::withMessages(['days_before_shipment' => __('fabricplanning.master.shipment_days')]);
        }
        if ($r['basis'] === 'PCD' && (int) ($r['days_before_pcd'] ?? 0) <= 0) {
            throw ValidationException::withMessages(['days_before_pcd' => __('fabricplanning.master.pcd_days')]);
        }

        return $data;
    }
}
