<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Resources;

use App\Modules\DyeHouse\Models\DyeBatch;
use App\Modules\DyeHouse\Models\DyeGreyIndent;
use App\Modules\DyeHouse\Support\DyeBatches;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Manual Dye Batch Entry: a batch card keyed in by hand where the plan could
 * not produce one — the reference it was raised against, the order and
 * shade, its own batch number, lot, date and weight. Its weight is the card's
 * one grey line, so it can be called off and split like any other card.
 */
class DyeManualBatchResource extends DyeBatchResource
{
    public function key(): string
    {
        return 'dye-manual-batches';
    }

    public function model(): string
    {
        return DyeBatch::class;
    }

    public function columns(): array
    {
        return ['manual_ref_no', 'manual_ref_date', 'buyer_id', 'order_id', 'colour_id', 'batch_no', 'lot_no', 'batch_date', 'card_qty', 'remarks'];
    }

    public function query(): Builder
    {
        return DyeBatch::query()->where('source', 'MANUAL')->orderByDesc('batch_date')->orderByDesc('batch_no');
    }

    public function rowQuery(): Builder
    {
        return DyeBatch::query()->where('source', 'MANUAL');
    }

    public function messages(): array
    {
        return [
            'manual_ref_no.required' => __('dyehouse.batch.v.manual_ref'), 'manual_ref_no.unique' => __('dyehouse.batch.v.manual_ref_taken'),
            'manual_ref_date.required' => __('dyehouse.batch.v.ref_date'), 'batch_no.required' => __('dyehouse.batch.v.batch_no'), 'batch_no.regex' => __('dyehouse.batch.v.code_chars'),
            'batch_date.required' => __('dyehouse.batch.v.batch_date'), 'card_qty.required' => __('dyehouse.batch.v.batch_qty'), 'card_qty.gt' => __('dyehouse.batch.v.batch_qty'),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'manual_ref_no' => [$required, 'string', 'max:40', Rule::unique('dye_batches', 'manual_ref_no')->where('company_id', $this->company())->ignore($row?->getKey())],
            'manual_ref_date' => [$required, 'date'],
            'buyer_id' => ['nullable', $this->own('buyers')],
            'order_id' => ['nullable', $this->own('merch_orders')],
            'colour_id' => ['nullable', $this->own('colours')],
            'batch_no' => [$required, 'string', 'max:24', 'regex:/^[A-Za-z0-9._-]+$/', $this->freeNumber($row)],
            'lot_no' => ['nullable', 'string', 'max:40'],
            'batch_date' => [$required, 'date'],
            'card_qty' => [$required, 'numeric', 'gt:0', 'max:99999999'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** A batch number no other card carries, however it is cased. */
    private function freeNumber(?Model $row): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($row): void {
            $taken = DyeBatch::query()->where('batch_no', DyeBatches::batchNo((string) $value))->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
            if ($taken) {
                $fail(__('dyehouse.batch.v.batch_no_taken', ['no' => DyeBatches::batchNo((string) $value)]));
            }
        };
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['batch_no'])) {
            $data['batch_no'] = DyeBatches::batchNo($data['batch_no']);
        }
        if (isset($data['manual_ref_no'])) {
            $data['manual_ref_no'] = trim($data['manual_ref_no']);
        }
        if (array_key_exists('card_qty', $data)) {
            $data['plan_qty'] = $data['card_qty'];
        }
        if (array_key_exists('order_id', $data)) {
            $order = $data['order_id'] ? DB::table('merch_orders')->where('id', $data['order_id'])->first(['order_no', 'style_no', 'work_style_no', 'buyer_id']) : null;
            $data['order_no'] = $order?->order_no;
            $data['style_ref'] = $order ? ($order->work_style_no ?: $order->style_no) : null;
            if ($order && empty($data['buyer_id'] ?? $row?->getAttribute('buyer_id'))) {
                $data['buyer_id'] = $order->buyer_id;
            }
        }
        if (array_key_exists('colour_id', $data)) {
            $data['colour_group_id'] = $data['colour_id'] ? DB::table('colours')->where('id', $data['colour_id'])->value('colour_group_id') : null;
        }
        if (! $row) {
            $data += ['scope' => 'BULK', 'source' => 'MANUAL', 'status' => 'OPEN', 'created_by' => $this->actingUserId()];
        }

        return $data;
    }

    public function afterSave(Model $row, bool $created): void
    {
        /** @var DyeBatch $row */
        DyeBatches::wholeLine($row, 'GREY', __('dyehouse.batch.line.manual', ['ref' => $row->manual_ref_no]));
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $n = $this->names();
        [$out['style'], $out['order_no']] = $this->styleAndOrder($row->getAttribute('order_id'));
        $out['buyer'] = $n['buyers'][$row->getAttribute('buyer_id')] ?? null;
        $out['colour'] = $n['colours'][$row->getAttribute('colour_id')] ?? null;
        $out['card_qty'] = (float) $row->getAttribute('card_qty');

        return $out;
    }

    public function beforeDelete(Model $row): void
    {
        if (DyeGreyIndent::query()->where('batch_id', $row->getKey())->exists()) {
            throw ValidationException::withMessages(['batch_no' => __('dyehouse.batch.v.batch_called_off', ['no' => $row->getAttribute('batch_no')])]);
        }
    }
}
