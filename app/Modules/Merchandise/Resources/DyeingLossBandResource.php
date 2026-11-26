<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Merchandise\Models\DyeingLossBand;
use App\Modules\Merchandise\Models\FabricType;
use App\Modules\Merchandise\Models\FiberComposition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Dyeing Process Loss Norms: process-wise dyeing loss percentages the fabric requirement is grossed up by. Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class DyeingLossBandResource extends MasterResource
{
    public function key(): string
    {
        return 'dyeing-loss-bands';
    }

    public function model(): string
    {
        return DyeingLossBand::class;
    }

    public function columns(): array
    {
        return ['fabric_type_id', 'fiber_composition_id', 'color_group', 'weight_low', 'weight_high', 'loss_percent', 'active'];
    }

    public function query(): Builder
    {
        return DyeingLossBand::query()->orderBy('fiber_composition_id')->orderBy('color_group')->orderBy('weight_low');
    }

    protected function upperCase(): array
    {
        return [];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = $this->company();

        return [
            'fabric_type_id' => ['nullable', Rule::exists('fabric_types', 'id')->where('company_id', $company)],
            'fiber_composition_id' => [$required, Rule::exists('fiber_compositions', 'id')->where('company_id', $company)],
            'color_group' => [$required, Rule::in(DyeingLossBand::COLOR_GROUPS)],
            'weight_low' => [$required, 'numeric', 'min:0'],
            'weight_high' => [$required, 'numeric', 'gt:weight_low'],
            'loss_percent' => [$required, 'numeric', 'min:0', 'max:100'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Bands are read lower-inclusive and higher-exclusive, so 1-200 and
     * 200-500 meet without overlapping; two active bands for the same fabric
     * type, composition and colour group may not share a weight.
     */
    public function beforeSave(array $data, ?Model $row): array
    {
        $band = array_merge($row?->only(['fabric_type_id', 'fiber_composition_id', 'color_group', 'weight_low', 'weight_high', 'active']) ?? [], $data);
        if ($band['active'] ?? true) {
            $clash = DyeingLossBand::query()
                ->where('active', true)
                ->where('fiber_composition_id', $band['fiber_composition_id'])
                ->where('color_group', $band['color_group'])
                ->where(fn ($q) => empty($band['fabric_type_id']) ? $q->whereNull('fabric_type_id') : $q->where('fabric_type_id', $band['fabric_type_id']))
                ->where('weight_low', '<', $band['weight_high'])
                ->where('weight_high', '>', $band['weight_low'])
                ->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))
                ->exists();
            if ($clash) {
                throw ValidationException::withMessages(['weight_low' => __('merchandise.master.band_overlap')]);
            }
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['fabric_type'] = $row->getAttribute('fabric_type_id') ? FabricType::query()->whereKey($row->getAttribute('fabric_type_id'))->value('name') : null;
        $out['composition'] = FiberComposition::query()->whereKey($row->getAttribute('fiber_composition_id'))->value('description');

        return $out;
    }
}
