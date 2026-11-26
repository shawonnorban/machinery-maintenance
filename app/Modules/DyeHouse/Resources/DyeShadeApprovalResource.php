<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Resources;

use App\Modules\DyeHouse\Models\DyeBatch;
use App\Modules\DyeHouse\Models\DyeProduction;
use App\Modules\DyeHouse\Models\DyeShadeApproval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Rib & Collar Shade Approval: rib and collar shade checked against the body
 * fabric, per batch and position in the lot (SL#) — body, rib and collar are
 * checked separately and can disagree, so each takes its own number. Grey and
 * finished are both recorded, in weight and rolls. Only a batch that has been
 * on a machine; its lot, buyer, style and shade fill from the card when left
 * blank, and a passed approval is dated.
 */
class DyeShadeApprovalResource extends DyeBatchResource
{
    public function key(): string
    {
        return 'dye-shade-approvals';
    }

    public function model(): string
    {
        return DyeShadeApproval::class;
    }

    public function viewPermission(): string
    {
        return 'dyehouse.qc.view';
    }

    public function managePermission(): string
    {
        return 'dyehouse.qc.manage';
    }

    public function columns(): array
    {
        return ['batch_id', 'lot_no', 'sl_no', 'b_qty', 'rp_qty', 'rcv_date', 'aprv_date', 'buyer_id', 'order_id', 'colour_id', 'g_qty', 'g_roll', 'fin_qty', 'fin_roll', 'status', 'remarks'];
    }

    public function query(): Builder
    {
        return DyeShadeApproval::query()->orderByDesc('created_at');
    }

    public function messages(): array
    {
        return ['batch_id.required' => __('dyehouse.qc.v.batch_no'), 'sl_no.required' => __('dyehouse.qc.v.sl_no'), 'status.required' => __('dyehouse.qc.v.status')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $qty = ['nullable', 'numeric', 'min:0', 'max:99999999'];

        return [
            'batch_id' => [$required, $this->own('dye_batches')],
            'lot_no' => ['nullable', 'string', 'max:40'],
            'sl_no' => [$required, 'integer', 'min:1', 'max:999'],
            'b_qty' => $qty, 'rp_qty' => $qty, 'g_qty' => $qty, 'fin_qty' => $qty,
            'g_roll' => ['nullable', 'integer', 'min:0', 'max:99999'], 'fin_roll' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'rcv_date' => ['nullable', 'date'],
            'aprv_date' => ['nullable', 'date', 'after_or_equal:rcv_date'],
            'buyer_id' => ['nullable', $this->own('buyers')],
            'order_id' => ['nullable', $this->own('merch_orders')],
            'colour_id' => ['nullable', $this->own('colours')],
            'status' => [$required, Rule::in(DyeShadeApproval::STATUSES)],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $get = fn (string $k) => array_key_exists($k, $data) ? $data[$k] : $row?->getAttribute($k);
        $batch = DyeBatch::query()->findOrFail($get('batch_id'));
        // A shade cannot be approved before it has been dyed.
        if (! DyeProduction::query()->where('batch_id', $batch->id)->exists()) {
            throw ValidationException::withMessages(['batch_id' => __('dyehouse.qc.v.not_dyed', ['no' => $batch->batch_no])]);
        }
        if (DyeShadeApproval::query()->where('batch_id', $batch->id)->where('sl_no', $get('sl_no'))->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists()) {
            throw ValidationException::withMessages(['sl_no' => __('dyehouse.qc.v.sl_twice', ['sl' => $get('sl_no'), 'no' => $batch->batch_no])]);
        }
        foreach (['lot_no' => $batch->lot_no, 'buyer_id' => $batch->buyer_id, 'order_id' => $batch->order_id, 'colour_id' => $batch->colour_id] as $k => $v) {
            if (blank($get($k))) {
                $data[$k] = $v;
            }
        }
        if ($get('status') === 'PASSED' && blank($get('aprv_date'))) {
            $data['aprv_date'] = $this->today();
        }
        if (! $row) {
            $data['created_by'] = $this->actingUserId();
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $n = $this->names();
        $out['batch_no'] = DyeBatch::query()->whereKey($row->getAttribute('batch_id'))->value('batch_no');
        $out['buyer'] = $n['buyers'][$row->getAttribute('buyer_id')] ?? null;
        [$style, $order] = $this->styleAndOrder($row->getAttribute('order_id'));
        $out['style_order'] = trim(($style ?? '').' / '.($order ?? ''), ' /') ?: null;
        $out['colour'] = $n['colours'][$row->getAttribute('colour_id')] ?? null;
        $out['created_by_name'] = $row->getAttribute('created_by') ? DB::table('users')->where('id', $row->getAttribute('created_by'))->value('name') : null;
        foreach (['b_qty', 'rp_qty', 'g_qty', 'fin_qty'] as $k) {
            $out[$k] = $row->getAttribute($k) === null ? null : (float) $row->getAttribute($k);
        }

        return $out;
    }
}
