<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Resources;

use App\Modules\DyeHouse\Models\DyeColourGroup;
use App\Modules\DyeHouse\Support\DyeHouseDefaults;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Colour Group Rate Update: every shade band, what a kilo costs to run in it
 * (water and wash, carried into costing), and whether a lab dip has to be
 * approved first. Added one by one; the grid saves the lot at once
 * (DyeColourGroupApiController). Dye House → Master Data (AnnotechERP 17/01).
 */
class DyeColourGroupResource extends DyeMasterResource
{
    public function key(): string
    {
        return 'dye-colour-groups';
    }

    public function model(): string
    {
        return DyeColourGroup::class;
    }

    public function columns(): array
    {
        return ['code', 'name', 'labdip_required', 'water_cost', 'wash_cost', 'display_order', 'active'];
    }

    public function booleans(): array
    {
        return ['labdip_required', 'active'];
    }

    public function defaults(): array
    {
        return ['active' => true, 'labdip_required' => false, 'water_cost' => 0, 'wash_cost' => 0, 'display_order' => 0];
    }

    public function deletable(): bool
    {
        return false;
    }

    public function query(): Builder
    {
        DyeHouseDefaults::colourGroups($this->company());

        return DyeColourGroup::query()->orderByDesc('active')->orderBy('display_order')->orderBy('code');
    }

    public function messages(): array
    {
        return ['code.required' => __('dyehouse.master.colour_code_required')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'code' => [$required, 'string', 'max:16', function (string $attribute, mixed $value, Closure $fail) use ($row): void {
                $taken = DyeColourGroup::query()->where('code', trim((string) $value))->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
                if ($taken) {
                    $fail(__('dyehouse.master.code_taken'));
                }
            }],
            'name' => [$required, 'string', 'max:255'],
            'labdip_required' => ['sometimes', 'boolean'],
            'water_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'wash_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        foreach (['water_cost', 'wash_cost', 'display_order'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === null) {
                $data[$field] = 0;
            }
        }
        if (isset($data['code'])) {
            $data['code'] = trim($data['code']);
        }

        return $data;
    }
}
