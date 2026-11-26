<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Resources;

use App\Modules\DyeHouse\Models\DyeBatch;
use App\Modules\DyeHouse\Models\DyeGreyIndent;
use App\Modules\DyeHouse\Models\DyeGreyIndentLine;
use App\Modules\DyeHouse\Models\DyeGreyIssue;
use App\Modules\DyeHouse\Support\DyeBatches;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Dye Batch from Finished Fabric: a batch raised against finished goods
 * rather than grey — a re-dye or a shade correction. Create Requisition
 * writes the card (its number issued then) and its requisition together, and
 * the requisition is on the finished-fabric store named, not the batch store.
 */
class DyeFinishedBatchResource extends DyeBatchResource
{
    public function key(): string
    {
        return 'dye-finished-batches';
    }

    public function model(): string
    {
        return DyeBatch::class;
    }

    public function columns(): array
    {
        return ['batch_no', 'batch_date', 'colour_id', 'card_qty', 'lot_no', 'dye_machine_id', 'store_id', 'buyer_id'];
    }

    public function query(): Builder
    {
        return DyeBatch::query()->where('source', 'FINISHED')->orderByDesc('batch_date')->orderByDesc('batch_no');
    }

    public function rowQuery(): Builder
    {
        return DyeBatch::query()->where('source', 'FINISHED');
    }

    public function messages(): array
    {
        return [
            'colour_id.required' => __('dyehouse.batch.v.colour'), 'card_qty.required' => __('dyehouse.batch.v.batch_qty'), 'card_qty.gt' => __('dyehouse.batch.v.batch_qty'),
            'store_id.required' => __('dyehouse.batch.v.receiving_store'),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'colour_id' => [$required, $this->own('colours')],
            'card_qty' => [$required, 'numeric', 'gt:0', 'max:99999999'],
            'lot_no' => ['nullable', 'string', 'max:40'],
            'dye_machine_id' => ['nullable', $this->own('dye_machines')],
            'store_id' => [$required, $this->own('stores')],
            'buyer_id' => ['nullable', $this->own('buyers')],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (array_key_exists('card_qty', $data)) {
            $data['plan_qty'] = $data['card_qty'];
        }
        if (isset($data['colour_id'])) {
            $data['colour_group_id'] = DB::table('colours')->where('id', $data['colour_id'])->value('colour_group_id');
        }
        if (! $row) {
            $today = $this->today();
            $data += ['batch_no' => DyeBatches::nextNumber('B-', 'dye_batches', 'batch_no', $today), 'batch_date' => $today, 'scope' => 'BULK', 'source' => 'FINISHED',
                'status' => 'OPEN', 'created_by' => $this->actingUserId()];
        }

        return $data;
    }

    /** The card's one finished-fabric line, and its requisition on the store named, kept in step. */
    public function afterSave(Model $row, bool $created): void
    {
        /** @var DyeBatch $row */
        $item = DyeBatches::wholeLine($row, 'FINISHED', __('dyehouse.batch.line.finished'));
        $indent = DyeGreyIndent::query()->where('batch_id', $row->id)->where('req_type', 'FINISHED')->first();
        if (! $indent) {
            $indent = DyeGreyIndent::query()->create([
                'req_no' => DyeBatches::nextNumber('GFR-', 'dye_grey_indents', 'req_no', $row->batch_date->toDateString()), 'req_date' => $row->batch_date->toDateString(),
                'req_type' => 'FINISHED', 'batch_id' => $row->id, 'colour_group_id' => $row->colour_group_id, 'store_id' => $row->store_id, 'created_by' => $this->actingUserId(),
            ]);
        } else {
            $indent->update(['store_id' => $row->store_id, 'colour_group_id' => $row->colour_group_id]);
        }
        DyeGreyIndentLine::query()->updateOrCreate(['indent_id' => $indent->id, 'batch_item_id' => $item->id], ['kind' => 'FINISHED', 'description' => $item->description, 'req_kg' => $item->qty_kg, 'sort_order' => 10]);
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $n = $this->names();
        $out['colour'] = $n['colours'][$row->getAttribute('colour_id')] ?? null;
        $out['machine'] = $n['machines'][$row->getAttribute('dye_machine_id')] ?? null;
        $out['store'] = $n['stores'][$row->getAttribute('store_id')] ?? null;
        $out['buyer'] = $n['buyers'][$row->getAttribute('buyer_id')] ?? null;
        $out['card_qty'] = (float) $row->getAttribute('card_qty');
        $out['requisitions'] = DyeGreyIndent::query()->where('batch_id', $row->getKey())->pluck('req_no')->implode(', ');

        return $out;
    }

    public function beforeDelete(Model $row): void
    {
        $indents = DyeGreyIndent::query()->where('batch_id', $row->getKey())->pluck('id');
        if (DyeGreyIssue::query()->whereIn('indent_id', $indents)->exists()) {
            throw ValidationException::withMessages(['batch_no' => __('dyehouse.batch.v.batch_issued', ['no' => $row->getAttribute('batch_no')])]);
        }
    }
}
