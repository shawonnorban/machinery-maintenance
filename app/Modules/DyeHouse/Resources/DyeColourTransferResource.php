<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Resources;

use App\Modules\DyeHouse\Models\DyeBatch;
use App\Modules\DyeHouse\Models\DyeBatchItem;
use App\Modules\DyeHouse\Models\DyeColourTransfer;
use App\Modules\DyeHouse\Support\DyeBatches;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Batch Colour Replacement Transfer: a planned batch reassigned to another
 * shade before it runs. The new card takes its own number, lot, date and
 * weight — no more than the old card carries — and what the old card was made
 * of, in that share; the old card is replaced and comes off its machine.
 * Editing the transfer keeps the new card in step.
 */
class DyeColourTransferResource extends DyeBatchResource
{
    public function key(): string
    {
        return 'dye-colour-transfers';
    }

    public function model(): string
    {
        return DyeColourTransfer::class;
    }

    public function columns(): array
    {
        return ['transfer_no', 'batch_id', 'transfer_date', 'new_batch_no', 'lot_no', 'batch_date', 'batch_qty', 'colour_id', 'remarks'];
    }

    public function deletable(): bool
    {
        return false;
    }

    public function query(): Builder
    {
        return DyeColourTransfer::query()->orderByDesc('transfer_date')->orderByDesc('transfer_no');
    }

    public function messages(): array
    {
        return [
            'batch_id.required' => __('dyehouse.batch.v.batch_pick'), 'transfer_date.required' => __('dyehouse.batch.v.transfer_date'),
            'new_batch_no.required' => __('dyehouse.batch.v.new_batch_no'), 'new_batch_no.regex' => __('dyehouse.batch.v.code_chars'), 'batch_date.required' => __('dyehouse.batch.v.batch_date'),
            'batch_qty.required' => __('dyehouse.batch.v.batch_qty'), 'batch_qty.gt' => __('dyehouse.batch.v.batch_qty'),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'batch_id' => $row ? ['prohibited'] : ['required', Rule::exists('dye_batches', 'id')->where('company_id', $this->company())->where('status', 'OPEN')],
            'transfer_date' => [$required, 'date'],
            'new_batch_no' => [$required, 'string', 'max:24', 'regex:/^[A-Za-z0-9._-]+$/'],
            'lot_no' => ['nullable', 'string', 'max:40'],
            'batch_date' => [$required, 'date'],
            'batch_qty' => [$required, 'numeric', 'gt:0', 'max:99999999'],
            'colour_id' => ['nullable', $this->own('colours')],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $old = DyeBatch::query()->findOrFail($data['batch_id'] ?? $row?->getAttribute('batch_id'));
        if (isset($data['new_batch_no'])) {
            $data['new_batch_no'] = DyeBatches::batchNo($data['new_batch_no']);
            $taken = DyeBatch::query()->where('batch_no', $data['new_batch_no'])->when($row?->getAttribute('new_batch_id'), fn ($q, $id) => $q->whereKeyNot($id))->exists();
            if ($taken) {
                throw ValidationException::withMessages(['new_batch_no' => __('dyehouse.batch.v.batch_no_taken', ['no' => $data['new_batch_no']])]);
            }
        }
        if (isset($data['batch_qty']) && (float) $data['batch_qty'] > (float) $old->card_qty + 0.005) {
            throw ValidationException::withMessages(['batch_qty' => __('dyehouse.batch.v.over_card', ['no' => $old->batch_no, 'qty' => (float) $old->card_qty])]);
        }
        if (! $row) {
            $data += ['transfer_no' => DyeBatches::nextNumber('CTR-', 'dye_colour_transfers', 'transfer_no', $data['transfer_date']), 'created_by' => $this->actingUserId()];
        }

        return $data;
    }

    public function afterSave(Model $row, bool $created): void
    {
        /** @var DyeColourTransfer $row */
        $old = DyeBatch::query()->findOrFail($row->batch_id);
        $colour = $row->colour_id ?: $old->colour_id;
        $values = ['batch_no' => $row->new_batch_no, 'batch_date' => $row->batch_date->toDateString(), 'lot_no' => $row->lot_no, 'plan_qty' => $row->batch_qty, 'card_qty' => $row->batch_qty,
            'colour_id' => $colour, 'colour_group_id' => $colour ? DB::table('colours')->where('id', $colour)->value('colour_group_id') : null];
        $new = $row->new_batch_id ? DyeBatch::query()->find($row->new_batch_id) : null;
        if ($new) {
            $new->update($values);
            $this->shareItems($old, $new, (float) $row->batch_qty, false);

            return;
        }
        $new = DyeBatch::query()->create($values + collect($old->only(['scope', 'plan_id', 'buyer_id', 'order_id', 'order_no', 'style_ref', 'dyeing_method', 'dye_machine_id', 'store_id']))->all()
            + ['source' => 'COLOUR', 'parent_id' => $old->id, 'status' => 'OPEN', 'created_by' => $row->created_by]);
        $this->shareItems($old, $new, (float) $row->batch_qty, true);
        $old->update(['status' => 'REPLACED', 'planned_start' => null, 'planned_end' => null]);
        $row->update(['new_batch_id' => $new->id]);
    }

    /** What the old card was made of, in the share of its weight the new card takes. */
    private function shareItems(DyeBatch $old, DyeBatch $new, float $qty, bool $fresh): void
    {
        $ratio = (float) $old->card_qty > 0 ? $qty / (float) $old->card_qty : 0;
        if (! $fresh) {
            DyeBatchItem::query()->where('batch_id', $new->id)->delete();
        }
        foreach (DyeBatchItem::query()->where('batch_id', $old->id)->orderBy('sort_order')->get() as $item) {
            DyeBatchItem::query()->create(['batch_id' => $new->id, 'kind' => $item->kind, 'description' => $item->description, 'qty_kg' => round((float) $item->qty_kg * $ratio, 3),
                'rolls' => (int) round($item->rolls * $ratio), 'pcs' => (int) round($item->pcs * $ratio), 'yds' => round((float) $item->yds * $ratio, 2), 'sort_order' => $item->sort_order]);
        }
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['batch_no'] = DyeBatch::query()->whereKey($row->getAttribute('batch_id'))->value('batch_no');
        $out['colour'] = $this->names()['colours'][$row->getAttribute('colour_id')] ?? null;
        $out['batch_qty'] = (float) $row->getAttribute('batch_qty');

        return $out;
    }
}
