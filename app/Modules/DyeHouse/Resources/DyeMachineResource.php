<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Resources;

use App\Modules\DyeHouse\Models\DyeMachine;
use App\Modules\DyeHouse\Models\DyeMachineList;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Tenancy\Models\Floor;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Dye Machine Profile: every dyeing machine on the floor — capacity,
 * efficiency, nozzles, make and origin, the shed and production floor it
 * stands on, whether it is on the books (Status) and what it is doing this
 * morning (Prod. Status). Dye House → Master Data (AnnotechERP 17/01).
 */
class DyeMachineResource extends DyeMasterResource
{
    public function key(): string
    {
        return 'dye-machines';
    }

    public function model(): string
    {
        return DyeMachine::class;
    }

    public function columns(): array
    {
        return ['machine_no', 'model', 'machine_type_id', 'brand_id', 'location_id', 'origin_country_id', 'capacity', 'capacity_unit', 'efficiency', 'sl_no', 'year', 'rpm', 'temperature', 'nozzles', 'is_sample', 'floor_id', 'mc_version', 'prod_status', 'active'];
    }

    public function booleans(): array
    {
        return ['is_sample', 'active'];
    }

    public function defaults(): array
    {
        return ['active' => true, 'is_sample' => false, 'prod_status' => 'FREE', 'mc_version' => '0'];
    }

    public function deletable(): bool
    {
        return false;
    }

    public function query(): Builder
    {
        return DyeMachine::query()->orderBy('machine_no');
    }

    public function messages(): array
    {
        return ['machine_type_id.required' => __('dyehouse.master.machine_type_required')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = $this->company();
        $list = fn (string $list) => Rule::exists('dye_machine_lists', 'id')->where('company_id', $company)->where('list', $list);

        return [
            'machine_no' => [$required, 'string', 'max:32', function (string $attribute, mixed $value, Closure $fail) use ($row): void {
                $taken = DyeMachine::query()->where('machine_no', strtoupper(trim((string) $value)))->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
                if ($taken) {
                    $fail(__('dyehouse.master.machine_taken'));
                }
            }],
            'model' => ['nullable', 'string', 'max:64'],
            'machine_type_id' => [$required, $list('TYPE')],
            'brand_id' => [$required, $list('BRAND')],
            'location_id' => ['nullable', $list('LOCATION')],
            'origin_country_id' => [$required, Rule::exists('origin_countries', 'id')->where('company_id', $company)],
            'capacity' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'capacity_unit' => [$required, Rule::in(DyeMachine::UNITS)],
            'efficiency' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sl_no' => [$required, 'string', 'max:64'],
            'year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'rpm' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'temperature' => ['nullable', 'integer', 'min:0', 'max:300'],
            'nozzles' => ['nullable', 'integer', 'min:0', 'max:100'],
            'is_sample' => ['sometimes', 'boolean'],
            'floor_id' => [$required, Rule::exists('floors', 'id')->where('company_id', $company)],
            'mc_version' => [$required, 'string', 'max:32'],
            'prod_status' => [$required, Rule::in(DyeMachine::PROD_STATUSES)],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['machine_no'])) {
            $data['machine_no'] = strtoupper(trim($data['machine_no']));
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $names = DyeMachineList::query()->whereIn('id', array_filter([$row->getAttribute('machine_type_id'), $row->getAttribute('brand_id'), $row->getAttribute('location_id')]))->pluck('name', 'id');
        $out['machine_type'] = $names[$row->getAttribute('machine_type_id')] ?? null;
        $out['brand'] = $names[$row->getAttribute('brand_id')] ?? null;
        $out['location'] = $row->getAttribute('location_id') ? ($names[$row->getAttribute('location_id')] ?? null) : null;
        $out['country'] = OriginCountry::query()->whereKey($row->getAttribute('origin_country_id'))->value('name');
        $out['floor'] = Floor::query()->whereKey($row->getAttribute('floor_id'))->value('code');

        return $out;
    }
}
