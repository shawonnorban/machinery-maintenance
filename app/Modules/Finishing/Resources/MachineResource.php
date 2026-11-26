<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Resources;

use App\Modules\DyeHouse\Models\DyeMachineList;
use App\Modules\DyeHouse\Support\DyeHouseDefaults;
use App\Modules\Finishing\Models\FinMachine;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Tenancy\Models\Floor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Finishing Machine Profile: each finishing machine's register entry — type,
 * make and origin, capacity and its unit, efficiency, status and the floor it
 * stands on. Capacity and efficiency are bounded, since the floor board plans
 * against them. A machine is deactivated, never deleted. Finishing Line →
 * Master Data (AnnotechERP 18/01).
 */
class MachineResource extends FinishingResource
{
    public function key(): string
    {
        return 'fin-machines';
    }

    public function model(): string
    {
        return FinMachine::class;
    }

    public function columns(): array
    {
        return ['machine_no', 'name', 'model', 'origin_country_id', 'brand_id', 'capacity', 'location_id', 'mou', 'efficiency', 'sl_no', 'machine_type', 'rpm', 'year', 'status', 'temperature', 'floor_id', 'is_sample', 'key_image', 'active'];
    }

    public function booleans(): array
    {
        return ['is_sample', 'active'];
    }

    public function defaults(): array
    {
        return ['status' => 'IDLE', 'is_sample' => false, 'active' => true];
    }

    public function query(): Builder
    {
        DyeHouseDefaults::machineLists($this->company());

        return FinMachine::query()->orderBy('machine_no');
    }

    public function messages(): array
    {
        return ['machine_type.required' => __('finishing.master.machine_type_required')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = $this->company();
        $list = fn (string $list) => Rule::exists('dye_machine_lists', 'id')->where('company_id', $company)->where('list', $list);

        return [
            'machine_type' => [$required, Rule::in(FinMachine::TYPES)],
            'machine_no' => [$required, 'string', 'max:32', $this->uniqueText(FinMachine::class, 'machine_no', $row, __('finishing.master.machine_taken'))],
            'name' => [$required, 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:64'],
            'origin_country_id' => [$required, Rule::exists('origin_countries', 'id')->where('company_id', $company)],
            'brand_id' => [$required, $list('BRAND')],
            'capacity' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'location_id' => ['nullable', $list('LOCATION')],
            'mou' => [$required, Rule::in(FinMachine::UNITS)],
            'efficiency' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sl_no' => ['nullable', 'string', 'max:64'],
            'rpm' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'status' => [$required, Rule::in(FinMachine::STATUSES)],
            'temperature' => ['nullable', 'integer', 'min:0', 'max:400'],
            'floor_id' => [$required, Rule::exists('floors', 'id')->where('company_id', $company)],
            'is_sample' => ['sometimes', 'boolean'],
            'key_image' => ['nullable', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $lists = DyeMachineList::query()->whereIn('id', array_filter([$row->getAttribute('brand_id'), $row->getAttribute('location_id')]))->pluck('name', 'id');
        $out['brand'] = $lists[$row->getAttribute('brand_id')] ?? null;
        $out['location'] = $lists[$row->getAttribute('location_id')] ?? null;
        $out['country'] = OriginCountry::query()->whereKey($row->getAttribute('origin_country_id'))->value('name');
        $out['floor'] = Floor::query()->whereKey($row->getAttribute('floor_id'))->value('name');

        return $out;
    }
}
