<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Resources;

use App\Modules\DyeHouse\Models\DyeBatch;
use App\Modules\DyeHouse\Models\DyeBatchItem;
use App\Modules\DyeHouse\Models\DyeGreyIndent;
use App\Modules\DyeHouse\Models\DyeGreyIndentLine;
use App\Modules\DyeHouse\Models\DyeGreyIssue;
use App\Modules\DyeHouse\Support\DyeBatches;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Bulk Grey Fabric Indent: grey fabric called off the batch store against a
 * bulk batch card, at the dyeing rate of the shade band it is dyed under. Its
 * lines are copied from the card, so what is called off is what the batch is
 * made of; a card is called off once. The store issues against it on the
 * Bulk Grey Fabric Issue Register, which reads these same rows.
 */
class DyeGreyIndentResource extends DyeBatchResource
{
    public function key(): string
    {
        return 'dye-grey-indents';
    }

    public function model(): string
    {
        return DyeGreyIndent::class;
    }

    /** The requisitions this screen keeps, and the cards they are raised against. */
    protected function type(): string
    {
        return 'BULK';
    }

    protected function scope(): string
    {
        return 'BULK';
    }

    public function columns(): array
    {
        return ['req_no', 'req_date', 'req_type', 'batch_id', 'colour_group_id', 'proj_rate', 'store_id'];
    }

    public function query(): Builder
    {
        return DyeGreyIndent::query()->where('req_type', $this->type())->orderByDesc('req_date')->orderByDesc('req_no');
    }

    public function rowQuery(): Builder
    {
        return DyeGreyIndent::query()->where('req_type', $this->type());
    }

    public function messages(): array
    {
        return [
            'batch_id.required' => __('dyehouse.batch.v.indent_batch'), 'colour_group_id.required' => __('dyehouse.batch.v.dyeing_rate'),
            'proj_rate.required' => __('dyehouse.batch.v.proj_rate'), 'store_id.required' => __('dyehouse.batch.v.receiving_store'),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'batch_id' => $row ? ['prohibited'] : ['required', Rule::exists('dye_batches', 'id')->where('company_id', $this->company())->where('scope', $this->scope())->where('status', 'OPEN')],
            'colour_group_id' => [$required, $this->own('dye_colour_groups')],
            'proj_rate' => [$required, 'numeric', 'min:0', 'max:99999999'],
            'store_id' => [$required, $this->own('stores')],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (! $row) {
            $taken = DyeGreyIndent::query()->where('batch_id', $data['batch_id'])->value('req_no');
            if ($taken) {
                throw ValidationException::withMessages(['batch_id' => __('dyehouse.batch.v.batch_called_off_on', ['no' => DyeBatch::query()->whereKey($data['batch_id'])->value('batch_no'), 'req' => $taken])]);
            }
            if (! DyeBatchItem::query()->where('batch_id', $data['batch_id'])->exists()) {
                throw ValidationException::withMessages(['batch_id' => __('dyehouse.batch.v.batch_empty')]);
            }
            $today = $this->today();
            $data += ['req_no' => DyeBatches::nextNumber('GFR-', 'dye_grey_indents', 'req_no', $today), 'req_date' => $today, 'req_type' => $this->type(), 'created_by' => $this->actingUserId()];
        }

        return $data;
    }

    /** The card's lines, copied once. */
    public function afterSave(Model $row, bool $created): void
    {
        if (! $created) {
            return;
        }
        foreach (DyeBatchItem::query()->where('batch_id', $row->getAttribute('batch_id'))->orderBy('sort_order')->get() as $i => $item) {
            DyeGreyIndentLine::query()->create(['indent_id' => $row->getKey(), 'batch_item_id' => $item->id, 'kind' => $item->kind, 'description' => $item->description,
                'req_kg' => $item->qty_kg, 'rolls' => $item->rolls, 'sort_order' => ($i + 1) * 10]);
        }
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $n = $this->names();
        $batch = DyeBatch::query()->whereKey($row->getAttribute('batch_id'))->first(['batch_no', 'buyer_id', 'order_id', 'colour_id']);
        $lines = DyeGreyIndentLine::query()->where('indent_id', $row->getKey())->get(['req_kg']);
        $out['batch_no'] = $batch?->batch_no;
        $out['buyer_id'] = $batch?->buyer_id;
        $out['order_id'] = $batch?->order_id;
        $out['colour_id'] = $batch?->colour_id;
        $out['store'] = $n['stores'][$row->getAttribute('store_id')] ?? null;
        $out['items'] = $lines->count();
        $out['req_kg'] = round((float) $lines->sum('req_kg'), 3);
        $out['issue_kg'] = round((float) DyeGreyIssue::query()->where('indent_id', $row->getKey())->sum('qty_kg'), 3);
        $out['proj_rate'] = $row->getAttribute('proj_rate') === null ? null : (float) $row->getAttribute('proj_rate');
        $out['company'] = DB::table('companies')->where('id', $row->getAttribute('party_id') ?? $this->company())->value('name');

        return $out;
    }

    public function beforeDelete(Model $row): void
    {
        if (DyeGreyIssue::query()->where('indent_id', $row->getKey())->exists()) {
            throw ValidationException::withMessages(['req_no' => __('dyehouse.batch.v.indent_issued', ['no' => $row->getAttribute('req_no')])]);
        }
    }
}
