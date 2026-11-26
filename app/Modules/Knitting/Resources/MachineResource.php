<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Resources;

use App\Modules\Knitting\Models\KnitMachine;
use App\Modules\Knitting\Models\KnitRefEntry;
use App\Modules\Knitting\Support\KnittingDefaults;
use App\Modules\Knitting\Support\KnittingLabels;
use App\Modules\Merchandise\Models\OriginCountry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Knitting Machine Profile: every knitting machine on the floor — its type,
 * make and origin, the diameter and gauge it runs today and every gauge it
 * can be re-needled to. A machine is withdrawn (Active? cleared), never
 * deleted. Knit Production → Master Data (AnnotechERP 15/01).
 */
class MachineResource extends KnittingResource
{
    public function key(): string
    {
        return 'knit-machines';
    }

    public function model(): string
    {
        return KnitMachine::class;
    }

    public function columns(): array
    {
        return ['machine_no', 'machine_type_id', 'brand_id', 'model', 'origin_country_id', 'serial_no', 'dia_id', 'gauge_id', 'supported_gauge_ids', 'capacity_kg', 'feeders', 'rpm', 'efficiency', 'floor_id', 'mfg_year', 'active'];
    }

    public function booleans(): array
    {
        return ['active'];
    }

    public function defaults(): array
    {
        return ['active' => true];
    }

    public function deletable(): bool
    {
        return false;
    }

    public function query(): Builder
    {
        KnittingDefaults::refLists($this->company());

        return KnitMachine::query()->orderBy('machine_no');
    }

    public function messages(): array
    {
        return ['machine_no.required' => __('knitting.master.machine_no_required')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = $this->company();
        $ref = fn (string $list) => Rule::exists('knit_ref_entries', 'id')->where('company_id', $company)->where('list', $list);

        return [
            'machine_no' => [$required, 'string', 'max:32', $this->uniqueText(KnitMachine::class, 'machine_no', $row, __('knitting.master.machine_taken'))],
            'machine_type_id' => [$required, $ref('MC_TYPE')],
            'brand_id' => [$required, $ref('BRAND')],
            'model' => ['nullable', 'string', 'max:255'],
            'origin_country_id' => [$required, Rule::exists('origin_countries', 'id')->where('company_id', $company)],
            'serial_no' => ['nullable', 'string', 'max:255'],
            'dia_id' => ['nullable', $ref('DIA')],
            'gauge_id' => ['nullable', $ref('GG')],
            'supported_gauge_ids' => ['nullable'],
            'capacity_kg' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'feeders' => ['nullable', 'integer', 'min:0', 'max:999'],
            'rpm' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'efficiency' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'floor_id' => [$required, Rule::exists('floors', 'id')->where('company_id', $company)],
            'mfg_year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (array_key_exists('supported_gauge_ids', $data) || ! $row) {
            $raw = $data['supported_gauge_ids'] ?? [];
            $ids = array_values(array_unique(array_filter(is_array($raw) ? $raw : explode(',', (string) $raw))));
            $known = KnitRefEntry::query()->where('list', 'GG')->whereIn('id', $ids)->count();
            if ($known !== count($ids)) {
                throw ValidationException::withMessages(['supported_gauge_ids' => __('validation.exists', ['attribute' => 'supported_gauge_ids'])]);
            }
            $data['supported_gauge_ids'] = $ids;
        }
        $gauge = array_key_exists('gauge_id', $data) ? $data['gauge_id'] : $row?->getAttribute('gauge_id');
        $supported = $data['supported_gauge_ids'] ?? $row?->getAttribute('supported_gauge_ids') ?? [];
        if ($gauge && ! in_array($gauge, $supported, true)) {
            throw ValidationException::withMessages(['gauge_id' => __('knitting.master.gauge_not_supported')]);
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $refs = KnittingLabels::refs();
        $out['supported_gauge_ids'] = implode(',', $row->getAttribute('supported_gauge_ids') ?? []);
        $out['machine_type'] = $refs[$row->getAttribute('machine_type_id')] ?? null;
        $out['brand'] = $refs[$row->getAttribute('brand_id')] ?? null;
        $out['dia'] = $refs[$row->getAttribute('dia_id')] ?? null;
        $out['gauge'] = $refs[$row->getAttribute('gauge_id')] ?? null;
        $out['supported'] = implode(', ', array_map(fn ($id) => $refs[$id] ?? '?', $row->getAttribute('supported_gauge_ids') ?? []));
        $out['floor'] = KnittingLabels::floors()[$row->getAttribute('floor_id')] ?? null;
        $out['country'] = OriginCountry::query()->whereKey($row->getAttribute('origin_country_id'))->value('name');

        return $out;
    }
}
