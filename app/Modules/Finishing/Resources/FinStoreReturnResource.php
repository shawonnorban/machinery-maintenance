<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Resources;

use App\Shared\Fabric\FabricLength;
use App\Modules\Finishing\Models\FinCutRequisition;
use App\Modules\Finishing\Models\FinCutRequisitionLine;
use App\Modules\Finishing\Models\FinStoreLot;
use App\Modules\Finishing\Models\FinStoreMove;
use App\Modules\Finishing\Models\FinStoreReturn;
use App\Modules\Finishing\Support\FinStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Fabric Store Return & Transfer: fabric returned from cutting into a lot —
 * against the requisition it went out on, never more than that requisition
 * released from the lot — or transferred from a lot to another unit, never
 * more than the lot holds. Either moves the lot's balance; editing re-books
 * it, removing takes it back. Brickland left this screen a scaffold; it is
 * built as its other registers are.
 */
class FinStoreReturnResource extends FinProductionResource
{
    public function key(): string
    {
        return 'fin-store-returns';
    }

    public function model(): string
    {
        return FinStoreReturn::class;
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
        return ['return_no', 'return_date', 'kind', 'lot_id', 'requisition_id', 'qty_kg', 'qty_m', 'rolls', 'to_unit', 'reason', 'remarks'];
    }

    public function query(): Builder
    {
        return FinStoreReturn::query()->orderByDesc('return_date')->orderByDesc('return_no');
    }

    public function messages(): array
    {
        return [
            'return_date.required' => __('finishing.store.v.return_date'), 'kind.required' => __('finishing.store.v.kind'), 'lot_id.required' => __('finishing.store.v.lot'),
            'qty_kg.required' => __('finishing.store.v.qty'), 'qty_kg.gt' => __('finishing.store.v.qty'), 'to_unit.required_if' => __('finishing.store.v.to_unit'), 'requisition_id.required_if' => __('finishing.store.v.requisition'),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'return_date' => [$required, 'date'],
            'kind' => [$required, Rule::in(FinStoreReturn::KINDS)],
            'lot_id' => [$required, $this->own('fin_store_lots')],
            'requisition_id' => ['nullable', 'required_if:kind,RETURN', $this->own('fin_cut_requisitions')],
            'qty_kg' => [$row ? 'sometimes' : 'nullable', 'required_without:qty_len', 'numeric', 'gt:0', 'max:99999999'],
            // Fabric by length (2026-11-16): in the company's yards or metres; the kilos follow from the lot when not given.
            'qty_len' => ['nullable', 'numeric', 'gt:0', 'max:99999999'],
            'rolls' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'to_unit' => ['nullable', 'required_if:kind,TRANSFER', 'string', 'max:120'],
            'reason' => ['nullable', 'string', 'max:120'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $get = fn (string $k) => array_key_exists($k, $data) ? $data[$k] : $row?->getAttribute($k);
        $lot = FinStoreLot::query()->findOrFail($get('lot_id'));
        $kgPerM = $lot->received_m ? (float) $lot->received_kg / (float) $lot->received_m : null;
        if (array_key_exists('qty_len', $data)) {
            $data['qty_m'] = FabricLength::toMetres($data['qty_len']);
            if (($data['qty_kg'] ?? null) === null && $data['qty_m'] !== null) {
                $data['qty_kg'] = $kgPerM ? round($data['qty_m'] * $kgPerM, 3) : FabricLength::metresToKg($data['qty_m'], $lot->fin_gsm, $lot->fin_dia);
                if ($data['qty_kg'] === null) {
                    throw ValidationException::withMessages(['qty_kg' => __('finishing.store.v.qty')]);
                }
            }
        } elseif (array_key_exists('qty_kg', $data) && $kgPerM) {
            $data['qty_m'] = round((float) $data['qty_kg'] / $kgPerM, 3);
        }
        unset($data['qty_len']);
        // An arrow function holds $data as it was made: read it afresh now the kilos and metres are filled in.
        $get = fn (string $k) => array_key_exists($k, $data) ? $data[$k] : $row?->getAttribute($k);
        $qty = (float) $get('qty_kg');
        $date = substr((string) $get('return_date'), 0, 10);
        if ($date < $lot->received_on->toDateString()) {
            throw ValidationException::withMessages(['return_date' => __('finishing.store.v.before_lot', ['lot' => $lot->lot_code])]);
        }
        if ($get('kind') === 'RETURN') {
            $req = FinCutRequisition::query()->findOrFail($get('requisition_id'));
            $issued = (float) FinCutRequisitionLine::query()->where('requisition_id', $req->id)->where('lot_id', $lot->id)->sum('issue_qty');
            $back = (float) FinStoreReturn::query()->where('kind', 'RETURN')->where('requisition_id', $req->id)->where('lot_id', $lot->id)->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->sum('qty_kg');
            if (! in_array($req->status, ['PARTIAL', 'COMPLETE'], true) || $issued <= 0) {
                throw ValidationException::withMessages(['requisition_id' => __('finishing.store.v.nothing_issued_from', ['no' => $req->req_no, 'lot' => $lot->lot_code])]);
            }
            if ($qty > $issued - $back + 0.0005) {
                throw ValidationException::withMessages(['qty_kg' => __('finishing.store.v.return_over', ['kg' => $this->kgText($issued - $back), 'no' => $req->req_no])]);
            }
            $data['to_unit'] = null;
        } else {
            $left = FinStore::balances([$lot->id], $row?->getKey())[$lot->id]['kg'] ?? 0.0;
            $leftM = FinStore::balances([$lot->id], $row?->getKey())[$lot->id]['m'] ?? 0.0;
            if ($lot->received_m !== null && (float) $get('qty_m') > $leftM + 0.0005) {
                throw ValidationException::withMessages(['qty_len' => __('finishing.store.v.row_over_length', ['n' => 1, 'lot' => $lot->lot_code, 'len' => rtrim(rtrim(number_format((float) FabricLength::fromMetres($leftM), 3, '.', ''), '0'), '.'), 'unit' => strtolower(FabricLength::unit())])]);
            }
            if ($qty > $left + 0.0005) {
                throw ValidationException::withMessages(['qty_kg' => __('finishing.store.v.transfer_over', ['lot' => $lot->lot_code, 'kg' => $this->kgText($left)])]);
            }
            $data['requisition_id'] = null;
        }
        if (! $row) {
            $data['return_no'] = $this->number('FSR-', 'fin_store_returns', 'return_no', $date);
            $data['created_by'] = $this->actingUserId();
        } else {
            unset($data['return_no']);
        }

        return $data;
    }

    public function afterSave(Model $row, bool $created): void
    {
        FinStore::unbook('RETURN', $row->getKey());
        $sign = $row->getAttribute('kind') === 'RETURN' ? 1 : -1;
        FinStore::book(FinStoreLot::query()->findOrFail($row->getAttribute('lot_id')), $row->getAttribute('kind'), $sign * (float) $row->getAttribute('qty_kg'), $sign * (int) ($row->getAttribute('rolls') ?? 0),
            $row->getAttribute('return_date')->toDateString(), 'RETURN', $row->getKey(), $row->getAttribute('return_no'), $this->actingUserId(),
            $row->getAttribute('qty_m') === null ? null : $sign * (float) $row->getAttribute('qty_m'));
    }

    public function beforeDelete(Model $row): void
    {
        if ($row->getAttribute('kind') === 'RETURN') {
            $lot = FinStoreLot::query()->findOrFail($row->getAttribute('lot_id'));
            $left = FinStore::balances([$lot->id], $row->getKey())[$lot->id]['kg'] ?? 0.0;
            if ($left < -0.0005) {
                throw ValidationException::withMessages(['return_no' => __('finishing.store.v.return_used', ['no' => $row->getAttribute('return_no')])]);
            }
        }
        FinStoreMove::query()->where('doc_type', 'RETURN')->where('doc_id', $row->getKey())->delete();
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $lot = FinStoreLot::query()->find($row->getAttribute('lot_id'));
        $out['lot_code'] = $lot?->lot_code;
        $out['lot_no'] = $lot?->lot_no;
        $out['colour'] = $lot?->colour;
        $out['req_no'] = $row->getAttribute('requisition_id') ? FinCutRequisition::query()->whereKey($row->getAttribute('requisition_id'))->value('req_no') : null;
        $out['qty_kg'] = $this->kg($row->getAttribute('qty_kg'));
        $out['balance_kg'] = $lot ? (FinStore::balances([$lot->id])[$lot->id]['kg'] ?? 0.0) : null;
        $out['qty_len'] = FabricLength::fromMetres($row->getAttribute('qty_m'));
        $out['balance_len'] = $lot && $lot->received_m !== null ? FabricLength::fromMetres(FinStore::balances([$lot->id])[$lot->id]['m'] ?? 0) : null;
        $out['len_unit'] = FabricLength::unit();

        return $out;
    }

    private function kgText(float $v): string
    {
        return rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');
    }
}
