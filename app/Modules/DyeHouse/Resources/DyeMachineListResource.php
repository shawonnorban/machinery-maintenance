<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Resources;

use App\Modules\DyeHouse\Models\DyeMachine;
use App\Modules\DyeHouse\Models\DyeMachineList;
use App\Modules\DyeHouse\Support\DyeHouseDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Machine Reference Lists: the machine types, brands and sheds the dye
 * machine form picks from. An entry a machine uses is switched off, not
 * deleted. Dye House → Master Data (AnnotechERP 17/01).
 */
class DyeMachineListResource extends DyeMasterResource
{
    private const COLUMN = ['TYPE' => 'machine_type_id', 'BRAND' => 'brand_id', 'LOCATION' => 'location_id'];

    public function key(): string
    {
        return 'dye-machine-lists';
    }

    public function model(): string
    {
        return DyeMachineList::class;
    }

    public function columns(): array
    {
        return ['list', 'name', 'sort_order', 'active'];
    }

    public function booleans(): array
    {
        return ['active'];
    }

    public function defaults(): array
    {
        return ['active' => true, 'sort_order' => 0];
    }

    public function query(): Builder
    {
        DyeHouseDefaults::machineLists($this->company());

        return DyeMachineList::query()->orderBy('list')->orderBy('sort_order')->orderBy('name');
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $list = request()->input('list', $row?->getAttribute('list'));

        return [
            'list' => [$row ? 'prohibited' : 'required', Rule::in(DyeMachineList::LISTS)],
            'name' => [$required, 'string', 'max:255', Rule::unique('dye_machine_lists', 'name')->where('company_id', $this->company())->where('list', $list)->ignore($row?->getKey())],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (array_key_exists('sort_order', $data) && $data['sort_order'] === null) {
            $data['sort_order'] = 0;
        }

        return $data;
    }

    public function beforeDelete(Model $row): void
    {
        if (DyeMachine::query()->where(self::COLUMN[$row->getAttribute('list')], $row->getKey())->exists()) {
            throw ValidationException::withMessages(['id' => __('dyehouse.master.in_use')]);
        }
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['machines'] = DyeMachine::query()->where(self::COLUMN[$row->getAttribute('list')], $row->getKey())->count();

        return $out;
    }
}
