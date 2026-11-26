<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Resources;

use App\Modules\DyeHouse\Models\DyeGreyOrderTransfer;
use App\Modules\DyeHouse\Support\DyeBatches;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Order-Wise Grey Transfer from Batch Store: grey fabric moved between orders
 * while it sits in the batch store — bulk to bulk, to or from sample, or out
 * to left-over — and where the transfer stands (10 Draft … 99 Cancelled).
 */
class DyeGreyOrderTransferResource extends DyeBatchResource
{
    public function key(): string
    {
        return 'dye-grey-order-transfers';
    }

    public function model(): string
    {
        return DyeGreyOrderTransfer::class;
    }

    public function columns(): array
    {
        return ['transfer_no', 'transfer_date', 'trans_type', 'buyer_id', 'from_order_id', 'to_order_id', 'status', 'remarks'];
    }

    public function query(): Builder
    {
        return DyeGreyOrderTransfer::query()->orderByDesc('transfer_date')->orderByDesc('transfer_no');
    }

    public function messages(): array
    {
        return [
            'transfer_date.required' => __('dyehouse.batch.v.date'), 'trans_type.required' => __('dyehouse.batch.v.trans_type'), 'status.required' => __('dyehouse.batch.v.status'),
            'to_order_id.different' => __('dyehouse.batch.v.same_order'),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'transfer_date' => [$required, 'date'],
            'trans_type' => [$required, Rule::in(DyeGreyOrderTransfer::TYPES)],
            'buyer_id' => ['nullable', $this->own('buyers')],
            'from_order_id' => ['nullable', $this->own('merch_orders')],
            'to_order_id' => ['nullable', $this->own('merch_orders'), 'different:from_order_id'],
            'status' => [$required, Rule::in(DyeGreyOrderTransfer::STATUSES)],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (! $row) {
            $data += ['transfer_no' => DyeBatches::nextNumber('GF/OTR/', 'dye_grey_order_transfers', 'transfer_no', $data['transfer_date']), 'created_by' => $this->actingUserId()];
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $n = $this->names();
        $label = fn (?string $id) => $id && isset($n['orders'][$id]) ? trim($n['orders'][$id]->order_no.' — '.($n['orders'][$id]->work_style_no ?: $n['orders'][$id]->style_no), ' —') : null;
        $out['buyer'] = $n['buyers'][$row->getAttribute('buyer_id')] ?? null;
        $out['from_order'] = $label($row->getAttribute('from_order_id'));
        $out['to_order'] = $label($row->getAttribute('to_order_id'));

        return $out;
    }
}
