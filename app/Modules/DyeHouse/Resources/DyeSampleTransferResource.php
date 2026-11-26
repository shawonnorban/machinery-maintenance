<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Resources;

use App\Modules\DyeHouse\Models\DyeBatch;
use App\Modules\DyeHouse\Models\DyeBatchItem;
use App\Modules\DyeHouse\Models\DyeSampleTransfer;
use App\Modules\DyeHouse\Support\DyeBatches;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Sample Batch from Bulk Lot: a sample drawn out of a bulk batch rather than
 * dyed on its own. The sample card takes its own number, lot, date and
 * weight — no more than the bulk card carries — and what the bulk card is
 * made of, in that share; what is drawn comes off the bulk card in the same
 * transaction, so the two always add up to what was planned. The transfer
 * number is the server's (SBB/YYYYMM###). Once drawn, only its lot, date and
 * remarks change.
 */
class DyeSampleTransferResource extends DyeBatchResource
{
    public function key(): string
    {
        return 'dye-sample-transfers';
    }

    public function model(): string
    {
        return DyeSampleTransfer::class;
    }

    public function columns(): array
    {
        return ['transfer_no', 'transfer_date', 'batch_id', 'new_batch_no', 'lot_no', 'batch_date', 'batch_qty', 'remarks'];
    }

    public function deletable(): bool
    {
        return false;
    }

    public function query(): Builder
    {
        return DyeSampleTransfer::query()->orderByDesc('transfer_date')->orderByDesc('transfer_no');
    }

    public function messages(): array
    {
        return [
            'transfer_date.required' => __('dyehouse.batch.v.transfer_date'), 'batch_id.required' => __('dyehouse.batch.v.batch_pick'),
            'new_batch_no.required' => __('dyehouse.batch.v.new_batch_no'), 'new_batch_no.regex' => __('dyehouse.batch.v.code_chars'),
            'batch_date.required' => __('dyehouse.batch.v.batch_date'), 'batch_qty.required' => __('dyehouse.batch.v.batch_qty'), 'batch_qty.gt' => __('dyehouse.batch.v.batch_qty'),
        ];
    }

    public function rules(?Model $row): array
    {
        if ($row) {
            return [
                'transfer_date' => ['sometimes', 'date'], 'lot_no' => ['nullable', 'string', 'max:40'], 'batch_date' => ['sometimes', 'date'], 'remarks' => ['nullable', 'string', 'max:2000'],
                'batch_id' => ['prohibited'], 'new_batch_no' => ['prohibited'], 'batch_qty' => ['prohibited'],
            ];
        }

        return [
            'transfer_date' => ['required', 'date'],
            'batch_id' => ['required', Rule::exists('dye_batches', 'id')->where('company_id', $this->company())->where('scope', 'BULK')->where('status', 'OPEN')],
            'new_batch_no' => ['required', 'string', 'max:24', 'regex:/^[A-Za-z0-9._-]+$/'],
            'lot_no' => ['nullable', 'string', 'max:40'],
            'batch_date' => ['required', 'date'],
            'batch_qty' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if ($row) {
            return $data;
        }
        $bulk = DyeBatch::query()->findOrFail($data['batch_id']);
        $data['new_batch_no'] = DyeBatches::batchNo($data['new_batch_no']);
        if (DyeBatch::query()->where('batch_no', $data['new_batch_no'])->exists()) {
            throw ValidationException::withMessages(['new_batch_no' => __('dyehouse.batch.v.batch_no_taken', ['no' => $data['new_batch_no']])]);
        }
        // Something stays on the bulk card: a sample is drawn off it, not the whole of it.
        if ((float) $data['batch_qty'] >= (float) $bulk->card_qty - 0.005) {
            throw ValidationException::withMessages(['batch_qty' => __('dyehouse.batch.v.sample_whole', ['no' => $bulk->batch_no, 'qty' => (float) $bulk->card_qty])]);
        }

        return $data + ['transfer_no' => DyeBatches::nextNumber('SBB/', 'dye_sample_transfers', 'transfer_no', $data['transfer_date'], 'Ym', 3), 'created_by' => $this->actingUserId()];
    }

    public function afterSave(Model $row, bool $created): void
    {
        /** @var DyeSampleTransfer $row */
        if (! $created) {
            if ($row->new_batch_id) {
                DyeBatch::query()->whereKey($row->new_batch_id)->update(['lot_no' => $row->lot_no, 'batch_date' => $row->batch_date->toDateString()]);
            }

            return;
        }
        $bulk = DyeBatch::query()->findOrFail($row->batch_id);
        $qty = (float) $row->batch_qty;
        $ratio = $qty / max(0.0001, (float) $bulk->card_qty);
        $sample = DyeBatch::query()->create(collect($bulk->only(['buyer_id', 'order_id', 'order_no', 'style_ref', 'colour_id', 'colour_group_id', 'dyeing_method']))->all() + [
            'batch_no' => $row->new_batch_no, 'batch_date' => $row->batch_date->toDateString(), 'scope' => 'SAMPLE', 'source' => 'FROM_BULK', 'parent_id' => $bulk->id,
            'lot_no' => $row->lot_no, 'plan_qty' => $qty, 'card_qty' => $qty, 'status' => 'OPEN', 'created_by' => $row->created_by,
        ]);
        foreach (DyeBatchItem::query()->where('batch_id', $bulk->id)->orderBy('sort_order')->get() as $item) {
            $take = ['qty_kg' => round((float) $item->qty_kg * $ratio, 3), 'rolls' => (int) round($item->rolls * $ratio), 'pcs' => (int) round($item->pcs * $ratio), 'yds' => round((float) $item->yds * $ratio, 2)];
            DyeBatchItem::query()->create(['batch_id' => $sample->id, 'kind' => $item->kind, 'description' => $item->description, 'sort_order' => $item->sort_order] + $take);
            $item->update(['qty_kg' => (float) $item->qty_kg - $take['qty_kg'], 'rolls' => max(0, $item->rolls - $take['rolls']), 'pcs' => max(0, $item->pcs - $take['pcs']), 'yds' => (float) $item->yds - $take['yds']]);
        }
        $bulk->update(['card_qty' => (float) $bulk->card_qty - $qty, 'plan_qty' => max(0, (float) $bulk->plan_qty - $qty)]);
        $row->update(['new_batch_id' => $sample->id]);
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['batch_no'] = DyeBatch::query()->whereKey($row->getAttribute('batch_id'))->value('batch_no');
        $out['batch_qty'] = (float) $row->getAttribute('batch_qty');

        return $out;
    }
}
