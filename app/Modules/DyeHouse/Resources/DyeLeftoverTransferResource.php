<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Resources;

use App\Modules\DyeHouse\Models\DyeLeftoverTransfer;
use App\Modules\DyeHouse\Support\DyeBatches;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Batch Transfer to Leftover: fabric released from a cancelled or short batch
 * into left-over stock — why, from where, for which order and shade, how
 * much, and where the release stands.
 */
class DyeLeftoverTransferResource extends DyeBatchResource
{
    public function key(): string
    {
        return 'dye-leftover-transfers';
    }

    public function model(): string
    {
        return DyeLeftoverTransfer::class;
    }

    public function columns(): array
    {
        return ['transfer_no', 'transfer_date', 'reason', 'source', 'buyer_id', 'order_id', 'colour_id', 'qty', 'status', 'remarks'];
    }

    public function query(): Builder
    {
        return DyeLeftoverTransfer::query()->orderByDesc('transfer_date')->orderByDesc('transfer_no');
    }

    public function messages(): array
    {
        return [
            'transfer_date.required' => __('dyehouse.batch.v.transfer_date'), 'reason.required' => __('dyehouse.batch.v.reason'), 'source.required' => __('dyehouse.batch.v.source'),
            'qty.required' => __('dyehouse.batch.v.trns_qty'), 'qty.gt' => __('dyehouse.batch.v.trns_qty'), 'status.required' => __('dyehouse.batch.v.status'),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'transfer_date' => [$required, 'date'],
            'reason' => [$required, Rule::in(DyeLeftoverTransfer::REASONS)],
            'source' => [$required, 'string', 'max:120'],
            'buyer_id' => ['nullable', $this->own('buyers')],
            'order_id' => ['nullable', $this->own('merch_orders')],
            'colour_id' => ['nullable', $this->own('colours')],
            'qty' => [$required, 'numeric', 'gt:0', 'max:99999999'],
            'status' => [$required, Rule::in(DyeLeftoverTransfer::STATUSES)],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (! empty($data['order_id']) && empty($data['buyer_id'] ?? $row?->getAttribute('buyer_id'))) {
            $data['buyer_id'] = DB::table('merch_orders')->where('id', $data['order_id'])->value('buyer_id');
        }
        if (! $row) {
            $data += ['transfer_no' => DyeBatches::nextNumber('BLT-', 'dye_leftover_transfers', 'transfer_no', $data['transfer_date']), 'created_by' => $this->actingUserId()];
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $n = $this->names();
        [$out['style'], $out['order_no']] = $this->styleAndOrder($row->getAttribute('order_id'));
        $out['buyer'] = $n['buyers'][$row->getAttribute('buyer_id')] ?? null;
        $out['colour'] = $n['colours'][$row->getAttribute('colour_id')] ?? null;
        $out['qty'] = (float) $row->getAttribute('qty');

        return $out;
    }
}
