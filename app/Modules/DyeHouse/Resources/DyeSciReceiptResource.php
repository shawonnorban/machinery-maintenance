<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Resources;

use App\Modules\DyeHouse\Models\DyeSciProgramme;
use App\Modules\DyeHouse\Models\DyeSciReceipt;
use App\Modules\DyeHouse\Support\ChemStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * In-House Sub-Contract Fabric Receipt: a party's fabric received in against
 * an in-house programme, on the party's own challan — its number once per
 * party, the party being the programme's. Not against a cancelled or
 * completed programme, nor dated before the programme.
 */
class DyeSciReceiptResource extends DyeBatchResource
{
    public function key(): string
    {
        return 'dye-sci-receipts';
    }

    public function model(): string
    {
        return DyeSciReceipt::class;
    }

    public function viewPermission(): string
    {
        return 'dyehouse.sc.view';
    }

    public function managePermission(): string
    {
        return 'dyehouse.sc.manage';
    }

    public function columns(): array
    {
        return ['challan_no', 'challan_date', 'programme_id', 'supplier_id', 'store_id', 'qty_kg', 'remarks'];
    }

    public function query(): Builder
    {
        return DyeSciReceipt::query()->orderByDesc('challan_date')->orderByDesc('created_at');
    }

    public function messages(): array
    {
        return [
            'challan_no.required' => __('dyehouse.sci.v.challan_no'), 'challan_date.required' => __('dyehouse.sci.v.challan_date'), 'programme_id.required' => __('dyehouse.sci.v.programme'),
            'qty_kg.required' => __('dyehouse.sci.v.qty'), 'qty_kg.gt' => __('dyehouse.sci.v.qty'),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'challan_no' => [$required, 'string', 'max:40'],
            'challan_date' => [$required, 'date'],
            'programme_id' => [$required, $this->own('dye_sci_programmes')],
            'store_id' => ['nullable', $this->own('stores')],
            'qty_kg' => [$required, 'numeric', 'gt:0', 'max:99999999'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $get = fn (string $k) => array_key_exists($k, $data) ? $data[$k] : $row?->getAttribute($k);
        $prog = DyeSciProgramme::query()->findOrFail($get('programme_id'));
        if (in_array($prog->status, ['CANCELLED', 'COMPLETED'], true) && (! $row || $row->getAttribute('programme_id') !== $prog->id)) {
            throw ValidationException::withMessages(['programme_id' => __('dyehouse.sci.v.programme_closed', ['no' => $prog->program_no])]);
        }
        if ((string) $get('challan_date') < $prog->prog_date->toDateString()) {
            throw ValidationException::withMessages(['challan_date' => __('dyehouse.sci.v.before_programme', ['no' => $prog->program_no])]);
        }
        $data['supplier_id'] = $prog->supplier_id;
        if (array_key_exists('challan_no', $data)) {
            $data['challan_no'] = mb_strtoupper(trim((string) $data['challan_no']));
        }
        $twin = DyeSciReceipt::query()->where('supplier_id', $prog->supplier_id)->where('challan_no', $data['challan_no'] ?? $get('challan_no'))
            ->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
        if ($twin) {
            throw ValidationException::withMessages(['challan_no' => __('dyehouse.sci.v.challan_twice', ['no' => $data['challan_no'] ?? $get('challan_no')])]);
        }
        if (! $row) {
            $data['created_by'] = $this->actingUserId();
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $prog = DyeSciProgramme::query()->find($row->getAttribute('programme_id'));
        $out['program_no'] = $prog?->program_no;
        $out['prog_date'] = $prog?->prog_date?->toDateString();
        $out['supplier'] = $this->names()['suppliers'][$row->getAttribute('supplier_id')] ?? null;
        $out['store'] = ChemStore::store($row->getAttribute('store_id'));
        $out['qty_kg'] = (float) $row->getAttribute('qty_kg');

        return $out;
    }
}
