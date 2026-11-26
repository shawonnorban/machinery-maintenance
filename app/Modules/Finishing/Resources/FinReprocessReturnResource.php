<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Resources;

use App\Modules\Finishing\Models\FinBatchReceipt;
use App\Modules\Finishing\Models\FinReprocessReturn;
use App\Modules\Finishing\Support\FinFloor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Batch Re-Process Return: a batch sent back to the Dye House for shade, GSM
 * or width correction, the reason recorded against it. Only a card on the
 * floor, not delivered or out at a sub-contractor; never more than was
 * received, nor before it was. The card leaves the floor — the batch can be
 * received again once re-dyed. Its card is fixed; undone only while the
 * batch has not been received again.
 */
class FinReprocessReturnResource extends FinProductionResource
{
    public function key(): string
    {
        return 'fin-reprocess-returns';
    }

    public function model(): string
    {
        return FinReprocessReturn::class;
    }

    public function columns(): array
    {
        return ['return_no', 'return_date', 'receipt_id', 'reason', 'qty_kg', 'rolls', 'remarks'];
    }

    public function query(): Builder
    {
        return FinReprocessReturn::query()->orderByDesc('return_date')->orderByDesc('return_no');
    }

    public function messages(): array
    {
        return [
            'return_date.required' => __('finishing.prod.v.return_date'), 'receipt_id.required' => __('finishing.prod.v.card'), 'reason.required' => __('finishing.prod.v.reason'),
            'qty_kg.required' => __('finishing.prod.v.return_qty'), 'qty_kg.gt' => __('finishing.prod.v.return_qty'), 'remarks.required_if' => __('finishing.prod.v.other_reason'),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'return_date' => [$required, 'date'],
            'receipt_id' => [$required, $this->own('fin_batch_receipts')],
            'reason' => [$required, Rule::in(FinReprocessReturn::REASONS)],
            'qty_kg' => [$required, 'numeric', 'gt:0', 'max:99999999'],
            'rolls' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'remarks' => ['nullable', 'required_if:reason,OTHER', 'string', 'max:2000'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if ($row && array_key_exists('receipt_id', $data) && $data['receipt_id'] !== $row->getAttribute('receipt_id')) {
            throw ValidationException::withMessages(['receipt_id' => __('finishing.prod.v.return_card_fixed', ['no' => $row->getAttribute('return_no')])]);
        }
        $get = fn (string $k) => array_key_exists($k, $data) ? $data[$k] : $row?->getAttribute($k);
        $card = FinBatchReceipt::query()->findOrFail($get('receipt_id'));
        $batch = FinFloor::batches([$card->batch_id])[$card->batch_id]->batch_no ?? '';
        if (! $row) {
            if ($card->status !== 'ON_FLOOR') {
                throw ValidationException::withMessages(['receipt_id' => __('finishing.prod.v.card_not_on_floor', ['no' => $card->receipt_no])]);
            }
            if (in_array($card->stage, FinFloor::DESK_STAGES, true)) {
                throw ValidationException::withMessages(['receipt_id' => __('finishing.prod.v.card_gone', ['batch' => $batch, 'stage' => __("finishing.prod.opt.stage.{$card->stage}")])]);
            }
        }
        if (substr((string) $get('return_date'), 0, 10) < $card->receive_date->toDateString()) {
            throw ValidationException::withMessages(['return_date' => __('finishing.prod.v.return_before_receipt', ['no' => $card->receipt_no])]);
        }
        if ((float) $get('qty_kg') > (float) $card->recv_qty_kg + 0.0005) {
            throw ValidationException::withMessages(['qty_kg' => __('finishing.prod.v.return_over', ['kg' => rtrim(rtrim(number_format((float) $card->recv_qty_kg, 3, '.', ''), '0'), '.')])]);
        }
        if (! $row) {
            $data['return_no'] = $this->number('FRP-', 'fin_reprocess_returns', 'return_no', substr((string) $data['return_date'], 0, 10));
            $data['created_by'] = $this->actingUserId();
        } else {
            unset($data['return_no']);
        }

        return $data;
    }

    public function afterSave(Model $row, bool $created): void
    {
        if ($created) {
            FinBatchReceipt::query()->whereKey($row->getAttribute('receipt_id'))->update(['status' => 'RETURNED', 'updated_at' => now()]);
        }
    }

    public function beforeDelete(Model $row): void
    {
        $card = FinBatchReceipt::query()->findOrFail($row->getAttribute('receipt_id'));
        if ($again = FinFloor::liveCard($card->batch_id, $card->id)) {
            throw ValidationException::withMessages(['return_no' => __('finishing.prod.v.received_again', ['no' => $row->getAttribute('return_no'), 'receipt' => $again->receipt_no])]);
        }
        $card->forceFill(['status' => 'ON_FLOOR'])->save();
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $card = FinBatchReceipt::query()->find($row->getAttribute('receipt_id'));
        $b = $card ? (FinFloor::batches([$card->batch_id])[$card->batch_id] ?? null) : null;
        $out['receipt_no'] = $card?->receipt_no;
        $out['receive_date'] = $card?->receive_date?->toDateString();
        $out['stage'] = $card?->stage;
        $out['batch_no'] = $b?->batch_no;
        $out['buyer'] = $b?->buyer;
        $out['order_no'] = $b?->order_no;
        $out['colour'] = $b?->colour;
        $out['recv_qty_kg'] = $this->kg($card?->recv_qty_kg);
        $out['qty_kg'] = $this->kg($row->getAttribute('qty_kg'));

        return $out;
    }
}
