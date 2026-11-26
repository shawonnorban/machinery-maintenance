<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Resources;

use App\Modules\DyeHouse\Support\DyeBatches;
use App\Modules\Finishing\Models\FinStoreLot;
use App\Modules\Finishing\Models\FinStoreRoll;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Roll-Wise Racking & Barcode: each roll of a store lot — its tag number
 * (once per lot, kept upper case), its weight, the rack and bin it sits in,
 * and the barcode it is given (numbered here, never typed). A lot's rolls
 * never weigh more than the lot received. Brickland left this screen a
 * scaffold; it is built as its other registers are.
 */
class FinStoreRollResource extends FinProductionResource
{
    public function key(): string
    {
        return 'fin-store-rolls';
    }

    public function model(): string
    {
        return FinStoreRoll::class;
    }

    public function viewPermission(): string
    {
        return 'finishing.store.view';
    }

    public function managePermission(): string
    {
        return 'finishing.store.manage';
    }

    public function columns(): array
    {
        return ['lot_id', 'roll_no', 'barcode', 'weight_kg', 'rack', 'bin', 'status', 'remarks'];
    }

    public function query(): Builder
    {
        return FinStoreRoll::query()->orderByDesc('created_at');
    }

    public function messages(): array
    {
        return ['lot_id.required' => __('finishing.store.v.lot'), 'roll_no.required' => __('finishing.store.v.roll_no'), 'weight_kg.required' => __('finishing.store.v.weight'), 'weight_kg.gt' => __('finishing.store.v.weight')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'lot_id' => [$required, $this->own('fin_store_lots')],
            'roll_no' => [$required, 'string', 'max:40'],
            'weight_kg' => [$required, 'numeric', 'gt:0', 'max:999999'],
            'rack' => ['nullable', 'string', 'max:24'],
            'bin' => ['nullable', 'string', 'max:24'],
            'status' => ['sometimes', Rule::in(FinStoreRoll::STATUSES)],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $get = fn (string $k) => array_key_exists($k, $data) ? $data[$k] : $row?->getAttribute($k);
        $lot = FinStoreLot::query()->findOrFail($get('lot_id'));
        $roll = mb_strtoupper(trim((string) $get('roll_no')));
        $data['roll_no'] = $roll;
        if (FinStoreRoll::query()->where('lot_id', $lot->id)->where('roll_no', $roll)->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists()) {
            throw ValidationException::withMessages(['roll_no' => __('finishing.store.v.roll_twice', ['roll' => $roll, 'lot' => $lot->lot_code])]);
        }
        $weighed = (float) FinStoreRoll::query()->where('lot_id', $lot->id)->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->sum('weight_kg');
        if ($weighed + (float) $get('weight_kg') > (float) $lot->received_kg + 0.0005) {
            throw ValidationException::withMessages(['weight_kg' => __('finishing.store.v.rolls_over_lot', ['lot' => $lot->lot_code, 'kg' => rtrim(rtrim(number_format((float) $lot->received_kg - $weighed, 3, '.', ''), '0'), '.')])]);
        }
        foreach (['rack', 'bin'] as $k) {
            if (array_key_exists($k, $data) && $data[$k] !== null) {
                $data[$k] = mb_strtoupper(trim((string) $data[$k])) ?: null;
            }
        }
        if (! $row) {
            $data['barcode'] = DyeBatches::nextNumber('FR', 'fin_store_rolls', 'barcode', now('Asia/Dhaka')->toDateString(), 'ym', 6);
            $data['status'] = 'IN_STORE';
        } else {
            unset($data['barcode']);
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $lot = FinStoreLot::query()->find($row->getAttribute('lot_id'));
        $out['lot_code'] = $lot?->lot_code;
        $out['lot_no'] = $lot?->lot_no;
        $out['colour'] = $lot?->colour;
        $out['order_no'] = $lot?->order_id ? DB::table('merch_orders')->where('id', $lot->order_id)->value('order_no') : null;
        $out['store'] = $lot ? DB::table('stores')->where('id', $lot->store_id)->value('name') : null;
        $out['weight_kg'] = $this->kg($row->getAttribute('weight_kg'));

        return $out;
    }
}
