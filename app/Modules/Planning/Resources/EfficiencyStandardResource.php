<?php

declare(strict_types=1);

namespace App\Modules\Planning\Resources;

use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Planning\Models\PlanningEfficiencyStandard;
use App\Modules\Planning\Support\PlanningLabels;
use App\Modules\Tenancy\Models\Factory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Efficiency Master: the efficiency a master or daily plan starts from, for a
 * style, a buyer, a line or a unit, from an effective date. The narrowest
 * standard wins — style, then buyer, then line, then unit. Each standard
 * names only what it applies to. Capacity Planning → Master Data
 * (AnnotechERP 12/01).
 */
class EfficiencyStandardResource extends PlanningMasterResource
{
    /** applies_to => the one column it names */
    private const TARGET = ['STYLE' => 'style_no', 'BUYER' => 'buyer_id', 'LINE' => 'planning_line_id', 'UNIT' => 'factory_id'];

    public function key(): string
    {
        return 'planning-efficiencies';
    }

    public function model(): string
    {
        return PlanningEfficiencyStandard::class;
    }

    public function columns(): array
    {
        return ['applies_to', 'factory_id', 'planning_line_id', 'buyer_id', 'style_no', 'efficiency', 'effective_from', 'remarks', 'active'];
    }

    public function booleans(): array
    {
        return ['active'];
    }

    public function defaults(): array
    {
        return ['active' => true];
    }

    public function query(): Builder
    {
        return PlanningEfficiencyStandard::query()->orderByDesc('effective_from');
    }

    public function messages(): array
    {
        return ['efficiency.between' => __('planning.master.efficiency_range'), 'efficiency.required' => __('planning.master.efficiency_range')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = $this->company();
        $scope = request()->input('applies_to', $row?->getAttribute('applies_to'));

        return [
            'applies_to' => [$required, Rule::in(PlanningEfficiencyStandard::APPLIES_TO)],
            'factory_id' => [$scope === 'UNIT' ? 'required' : 'nullable', Rule::exists('factories', 'id')->where('company_id', $company)],
            'planning_line_id' => [$scope === 'LINE' ? 'required' : 'nullable', Rule::exists('planning_lines', 'id')->where('company_id', $company)],
            'buyer_id' => [$scope === 'BUYER' ? 'required' : 'nullable', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'style_no' => [$scope === 'STYLE' ? 'required' : 'nullable', 'string', 'max:64'],
            'efficiency' => [$required, 'numeric', 'between:0,150'],
            'effective_from' => [$required, 'date'],
            'remarks' => ['nullable', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /** A standard keeps only the column its scope names. */
    public function beforeSave(array $data, ?Model $row): array
    {
        $scope = $data['applies_to'] ?? $row?->getAttribute('applies_to');
        foreach (self::TARGET as $applies => $column) {
            if ($applies !== $scope) {
                $data[$column] = null;
            }
        }
        if (! empty($data['style_no'])) {
            $data['style_no'] = strtoupper(trim($data['style_no']));
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['target'] = match ($row->getAttribute('applies_to')) {
            'STYLE' => $row->getAttribute('style_no'),
            'BUYER' => Buyer::query()->whereKey($row->getAttribute('buyer_id'))->value('short_name'),
            'LINE' => PlanningLabels::forLine($row->getAttribute('planning_line_id'))['line'],
            'UNIT' => Factory::query()->whereKey($row->getAttribute('factory_id'))->value('code'),
            default => null,
        };

        return $out;
    }
}
