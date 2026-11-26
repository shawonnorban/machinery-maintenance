<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Resources;

use App\Modules\Finishing\Models\FinBatchReceipt;
use App\Modules\Finishing\Models\FinShadeDecision;
use App\Modules\Finishing\Support\FinFloor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Shade, GSM & Width Test: a finished batch's decision against the buyer's
 * approved standard — one per batch (a batch already on the queue is opened,
 * not added twice). Its buyer, order, style, colour and quantity follow the
 * batch unless given. Deciding (Approved or Rejected) stamps who and when —
 * the one decided by, or whoever saved it; back to Pending clears both.
 */
class FinShadeDecisionResource extends FinQcResource
{
    public function key(): string
    {
        return 'fin-shade-decisions';
    }

    public function model(): string
    {
        return FinShadeDecision::class;
    }

    public function columns(): array
    {
        return ['receipt_id', 'buyer_id', 'order_id', 'style', 'colour', 'lot_no', 'batch_qty', 'model_continuity', 'status', 'decided_by', 'remarks'];
    }

    public function query(): Builder
    {
        return FinShadeDecision::query()->orderByRaw("case when status = 'PENDING' then 0 else 1 end")->orderByDesc('created_at');
    }

    public function messages(): array
    {
        return ['receipt_id.required' => __('finishing.qc.v.batch'), 'status.required' => __('finishing.qc.v.status')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'receipt_id' => [$required, $this->own('fin_batch_receipts')],
            'buyer_id' => ['nullable', $this->own('buyers')],
            'order_id' => ['nullable', $this->own('merch_orders')],
            'style' => ['nullable', 'string', 'max:120'],
            'colour' => ['nullable', 'string', 'max:120'],
            'lot_no' => ['nullable', 'string', 'max:40'],
            'batch_qty' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'model_continuity' => ['nullable', 'string', 'max:120'],
            'status' => [$required, Rule::in(FinShadeDecision::STATUSES)],
            'decided_by' => ['nullable', $this->member()],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if ($row && array_key_exists('receipt_id', $data) && $data['receipt_id'] !== $row->getAttribute('receipt_id')) {
            throw ValidationException::withMessages(['receipt_id' => __('finishing.qc.v.decision_batch_fixed')]);
        }
        if (! $row) {
            $card = $this->liveCard($data['receipt_id'], 'receipt_id');
            $b = FinFloor::batches([$card->batch_id])[$card->batch_id] ?? null;
            if ($twin = FinShadeDecision::query()->where('receipt_id', $card->id)->first()) {
                throw ValidationException::withMessages(['receipt_id' => __('finishing.qc.v.on_queue', ['batch' => $b->batch_no ?? ''])]);
            }
            foreach (['buyer_id' => $b?->buyer_id, 'order_id' => $b?->order_id, 'style' => $b?->style, 'colour' => $b?->colour, 'batch_qty' => (float) $card->recv_qty_kg] as $k => $v) {
                if (($data[$k] ?? null) === null || $data[$k] === '') {
                    $data[$k] = $v;
                }
            }
            $data['created_by'] = $this->actingUserId();
        }
        $status = $data['status'] ?? $row?->getAttribute('status');
        $was = $row?->getAttribute('status') ?? 'PENDING';
        if ($status === 'PENDING') {
            $data['decided_by'] = null;
            $data['decided_at'] = null;
        } elseif (! $row || $status !== $was) {
            // Newly decided: by the one named, or by whoever saved it — now.
            $data['decided_by'] = ($data['decided_by'] ?? null) ?: $this->actingUserId();
            $data['decided_at'] = now();
        } elseif (array_key_exists('decided_by', $data) && ! $data['decided_by']) {
            unset($data['decided_by']);
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $card = $this->cardInfo($row->getAttribute('receipt_id'));
        $out['receipt_no'] = $card['receipt_no'];
        $out['batch_no'] = $card['batch_no'];
        $out['buyer'] = $row->getAttribute('buyer_id') ? DB::table('buyers')->where('id', $row->getAttribute('buyer_id'))->value(DB::raw("coalesce(nullif(short_name, ''), name)")) : null;
        $out['order_no'] = $row->getAttribute('order_id') ? DB::table('merch_orders')->where('id', $row->getAttribute('order_id'))->value('order_no') : null;
        $out['decided_by_name'] = $row->getAttribute('decided_by') ? DB::table('users')->where('id', $row->getAttribute('decided_by'))->value('name') : null;
        $out['decided_at'] = $row->getAttribute('decided_at')?->toIso8601String();
        $out['batch_qty'] = $this->kg($row->getAttribute('batch_qty'));
        $out['stage'] = FinBatchReceipt::query()->whereKey($row->getAttribute('receipt_id'))->value('stage');

        return $out;
    }
}
