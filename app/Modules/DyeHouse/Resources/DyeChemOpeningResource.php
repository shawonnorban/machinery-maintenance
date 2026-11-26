<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Resources;

use App\Modules\DyeHouse\Models\DyeChemMove;
use App\Modules\DyeHouse\Models\DyeChemOpening;
use App\Modules\DyeHouse\Support\ChemStore;
use App\Modules\DyeHouse\Support\DyeBatches;
use App\Modules\Inventory\Support\UomCodes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Chemical Opening Balance: what the chemical store was holding on the day it
 * opened its books, one row per lot per store. The total is what the store
 * counted — left blank it is packs × qty per pack plus the loose — and the
 * rate is kept on the row. Saving books the lot's opening into stock; once
 * anything else has moved on the lot, its opening stays as counted.
 */
class DyeChemOpeningResource extends DyeBatchResource
{
    /** @var array<string, string>|null */
    private ?array $items = null;

    /** @var array<string, string>|null */
    private ?array $categories = null;

    public function key(): string
    {
        return 'dye-chem-openings';
    }

    public function model(): string
    {
        return DyeChemOpening::class;
    }

    public function viewPermission(): string
    {
        return 'dyehouse.chemstore.view';
    }

    public function managePermission(): string
    {
        return 'dyehouse.chemstore.manage';
    }

    public function columns(): array
    {
        return ['party_id', 'store_id', 'ob_date', 'category_id', 'usd_rate', 'item_id', 'source', 'auto_lot', 'lot_no', 'exp_date', 'pack_qty', 'pack_uom', 'qty_per_pack', 'loose_qty', 'total_qty', 'uom', 'rate_bdt'];
    }

    public function booleans(): array
    {
        return ['auto_lot'];
    }

    public function query(): Builder
    {
        return DyeChemOpening::query()->orderByDesc('ob_date')->orderBy('lot_no');
    }

    public function messages(): array
    {
        return [
            'store_id.required' => __('dyehouse.chem.v.store'), 'ob_date.required' => __('dyehouse.chem.v.ob_date'), 'item_id.required' => __('dyehouse.chem.v.item'),
            'source.required' => __('dyehouse.chem.v.source'), 'lot_no.required_unless' => __('dyehouse.chem.v.lot'),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $qty = ['nullable', 'numeric', 'min:0', 'max:99999999'];

        return [
            'party_id' => ['nullable', Rule::in([$this->company()])],
            'store_id' => [$required, $this->own('stores')],
            'ob_date' => [$required, 'date'],
            'category_id' => ['nullable', $this->own('item_categories')],
            'usd_rate' => ['nullable', 'numeric', 'gt:0', 'max:99999'],
            'item_id' => [$required, $this->own('items')],
            'source' => [$required, Rule::in(['LOCAL', 'IMPORT'])],
            'auto_lot' => ['boolean'],
            'lot_no' => $row ? ['sometimes', 'required', 'string', 'max:40'] : ['nullable', 'required_unless:auto_lot,1,true', 'string', 'max:40'],
            'exp_date' => ['nullable', 'date'],
            'pack_qty' => $qty, 'qty_per_pack' => $qty, 'loose_qty' => $qty, 'total_qty' => $qty,
            'pack_uom' => ['nullable', 'string', 'max:16', UomCodes::rule($this->company())],
            'uom' => ['nullable', 'string', 'max:16', UomCodes::rule($this->company())],
            'rate_bdt' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $date = (string) ($data['ob_date'] ?? $row?->getAttribute('ob_date')?->toDateString());
        ChemStore::assertOpen($date, 'ob_date');
        if ($row) {
            ChemStore::assertOpen($row->getAttribute('ob_date')->toDateString(), 'ob_date');
            $this->assertUnmoved($row);
        }
        $get = fn (string $k) => array_key_exists($k, $data) ? $data[$k] : $row?->getAttribute($k);
        // Ticked, the store gives the drum a lot number of its own.
        if (($data['auto_lot'] ?? false) && ! $row?->getAttribute('auto_lot')) {
            $data['lot_no'] = DyeBatches::nextNumber('OB', 'dye_chem_openings', 'lot_no', $date);
        }
        if (array_key_exists('lot_no', $data)) {
            $data['lot_no'] = ChemStore::lotNo($data['lot_no']);
        }
        // What the store counted; blank, the packs and the loose added up.
        if (($data['total_qty'] ?? null) === null && ($row === null || array_key_exists('total_qty', $data) || array_key_exists('pack_qty', $data) || array_key_exists('loose_qty', $data))) {
            $data['total_qty'] = round((float) $get('pack_qty') * (float) $get('qty_per_pack') + (float) $get('loose_qty'), 4);
        }
        if ((float) ($data['total_qty'] ?? $get('total_qty')) <= 0) {
            throw ValidationException::withMessages(['total_qty' => __('dyehouse.chem.v.total_qty')]);
        }
        foreach (['uom', 'pack_uom'] as $k) {
            if (array_key_exists($k, $data)) {
                $data[$k] = UomCodes::normalize($data[$k]);
            }
        }
        $data['rate_bdt'] = (float) ($data['rate_bdt'] ?? $get('rate_bdt') ?? 0);
        $clash = DyeChemOpening::query()->where('store_id', $get('store_id'))->where('item_id', $get('item_id'))->where('lot_no', $data['lot_no'] ?? $get('lot_no'))
            ->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
        if ($clash) {
            throw ValidationException::withMessages(['lot_no' => __('dyehouse.chem.v.ob_twice', ['lot' => $data['lot_no'] ?? $get('lot_no')])]);
        }
        if (! $row) {
            $data['created_by'] = $this->actingUserId();
        }

        return $data;
    }

    public function afterSave(Model $row, bool $created): void
    {
        /** @var DyeChemOpening $row */
        $old = $row->lot_id;
        $lot = ChemStore::lot($row->store_id, $row->item_id, $row->lot_no, ['exp_date' => $row->exp_date?->toDateString(), 'uom' => $row->uom, 'rate_bdt' => $row->rate_bdt, 'source' => $row->source]);
        $lot->update(['exp_date' => $row->exp_date, 'uom' => $row->uom, 'rate_bdt' => $row->rate_bdt, 'source' => $row->source]);
        ChemStore::unpost('OPENING', $this->docNo($row));
        ChemStore::add($lot->refresh(), 'OPENING', $this->docNo($row), $row->id, $row->ob_date->toDateString(), (float) $row->total_qty, null, $this->actingUserId());
        $row->forceFill(['lot_id' => $lot->id])->saveQuietly();
        if ($old && $old !== $lot->id && ! DyeChemMove::query()->where('lot_id', $old)->exists()) {
            DB::table('dye_chem_lots')->where('id', $old)->whereNotExists(fn ($q) => $q->from('dye_chem_receipt_lines')->whereColumn('lot_id', 'dye_chem_lots.id'))->delete();
        }
    }

    public function beforeDelete(Model $row): void
    {
        ChemStore::assertOpen($row->getAttribute('ob_date')->toDateString(), 'ob_date');
        $this->assertUnmoved($row);
        ChemStore::unpost('OPENING', $this->docNo($row));
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $n = $this->names();
        $out['company'] = $n['companies'][$row->getAttribute('party_id') ?? $this->company()] ?? null;
        $out['store'] = $n['stores'][$row->getAttribute('store_id')] ?? null;
        $this->items ??= DB::table('items')->where('company_id', $this->company())->get(['id', 'name', 'item_code'])->mapWithKeys(fn ($i) => [$i->id => "{$i->name} ({$i->item_code})"])->all();
        $this->categories ??= DB::table('item_categories')->where('company_id', $this->company())->pluck('name', 'id')->all();
        $out['item'] = $this->items[$row->getAttribute('item_id')] ?? null;
        $out['category'] = $this->categories[$row->getAttribute('category_id')] ?? null;
        foreach (['usd_rate', 'pack_qty', 'qty_per_pack', 'loose_qty', 'total_qty', 'rate_bdt'] as $k) {
            $out[$k] = $row->getAttribute($k) === null ? null : (float) $row->getAttribute($k);
        }
        $out['value'] = round((float) $row->getAttribute('total_qty') * (float) $row->getAttribute('rate_bdt'), 2);
        $out['moved'] = $row->getAttribute('lot_id') ? DyeChemMove::query()->where('lot_id', $row->getAttribute('lot_id'))->where('kind', '!=', 'OPENING')->exists() : false;

        return $out;
    }

    private function docNo(Model $row): string
    {
        return 'OB-'.$row->getKey();
    }

    /** Once anything else has moved on the lot, its opening stays as counted. */
    private function assertUnmoved(Model $row): void
    {
        $lot = $row->getAttribute('lot_id');
        if ($lot && DyeChemMove::query()->where('lot_id', $lot)->where('kind', '!=', 'OPENING')->exists()) {
            throw ValidationException::withMessages(['lot_no' => __('dyehouse.chem.v.ob_moved', ['lot' => $row->getAttribute('lot_no')])]);
        }
    }
}
