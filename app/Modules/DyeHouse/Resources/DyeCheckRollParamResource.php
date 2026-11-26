<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Resources;

use App\Modules\DyeHouse\Models\DyeCheckRollParam;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Check Roll Parameter Setup (AnnotechERP 17/07; a scaffold in Brickland,
 * built here): the GSM, width, shade (ΔE) and shrinkage tolerances a check
 * roll is measured against, per buyer and fabric type — a blank buyer or
 * fabric type is the parameter for every one. One parameter per pair.
 */
class DyeCheckRollParamResource extends DyeBatchResource
{
    public function key(): string
    {
        return 'dye-check-roll-params';
    }

    public function model(): string
    {
        return DyeCheckRollParam::class;
    }

    public function viewPermission(): string
    {
        return 'dyehouse.production.view';
    }

    public function managePermission(): string
    {
        return 'dyehouse.production.manage';
    }

    public function columns(): array
    {
        return ['buyer_id', 'fabric_type_id', 'gsm_tol_pct', 'width_tol_pct', 'shade_delta_e', 'shrink_length_pct', 'shrink_width_pct', 'remarks', 'active'];
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
        return DyeCheckRollParam::query()->orderByRaw('buyer_id is not null')->orderByRaw('fabric_type_id is not null')->orderBy('created_at');
    }

    public function messages(): array
    {
        return [
            'gsm_tol_pct.required' => __('dyehouse.prod.v.gsm_tol'), 'width_tol_pct.required' => __('dyehouse.prod.v.width_tol'), 'shade_delta_e.required' => __('dyehouse.prod.v.shade_tol'),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $pct = ['numeric', 'min:0', 'max:100'];

        return [
            'buyer_id' => ['nullable', $this->own('buyers')],
            'fabric_type_id' => ['nullable', $this->own('fabric_types')],
            'gsm_tol_pct' => [$required, ...$pct],
            'width_tol_pct' => [$required, ...$pct],
            'shade_delta_e' => [$required, 'numeric', 'min:0', 'max:20'],
            'shrink_length_pct' => ['nullable', ...$pct],
            'shrink_width_pct' => ['nullable', ...$pct],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'active' => ['boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $get = fn (string $k) => array_key_exists($k, $data) ? $data[$k] : $row?->getAttribute($k);
        $twin = DyeCheckRollParam::query()
            ->when($get('buyer_id'), fn ($q, $v) => $q->where('buyer_id', $v), fn ($q) => $q->whereNull('buyer_id'))
            ->when($get('fabric_type_id'), fn ($q, $v) => $q->where('fabric_type_id', $v), fn ($q) => $q->whereNull('fabric_type_id'))
            ->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
        if ($twin) {
            throw ValidationException::withMessages(['fabric_type_id' => __('dyehouse.prod.v.param_twice')]);
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['buyer'] = $this->names()['buyers'][$row->getAttribute('buyer_id')] ?? null;
        $out['fabric_type'] = $row->getAttribute('fabric_type_id') ? DB::table('fabric_types')->where('id', $row->getAttribute('fabric_type_id'))->value('name') : null;
        foreach (['gsm_tol_pct', 'width_tol_pct', 'shade_delta_e', 'shrink_length_pct', 'shrink_width_pct'] as $k) {
            $out[$k] = $row->getAttribute($k) === null ? null : (float) $row->getAttribute($k);
        }

        return $out;
    }
}
