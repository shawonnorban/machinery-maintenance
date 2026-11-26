<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Resources;

use App\Modules\Finishing\Models\FinBatchReceipt;
use App\Modules\Finishing\Models\FinProcessDelivery;
use App\Modules\Finishing\Models\FinProductionLine;
use App\Modules\Finishing\Models\FinReprocessReturn;
use App\Modules\Finishing\Support\FinFloor;
use App\Modules\Tenancy\Models\Floor;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Batch Receipt from Dye House: a dyed batch handed over from the dyeing
 * floor, roll and weight checked against the challan. Only a batch dyed at
 * least once, not replaced or closed, and not on the floor already — and not
 * before it was dyed. The receipt is the batch's finishing card: it starts
 * at Awaiting Load. Its batch is fixed once production is entered against
 * it; a returned card reads only; a card with production or a return stays.
 */
class FinBatchReceiptResource extends FinProductionResource
{
    public function key(): string
    {
        return 'fin-batch-receipts';
    }

    public function model(): string
    {
        return FinBatchReceipt::class;
    }

    public function columns(): array
    {
        return ['receipt_no', 'receive_date', 'batch_id', 'dia_type', 'booking_type', 'challan_no', 'challan_qty_kg', 'challan_rolls', 'recv_qty_kg', 'recv_rolls', 'floor_id', 'stage', 'status', 'remarks'];
    }

    public function query(): Builder
    {
        return FinBatchReceipt::query()->orderByDesc('receive_date')->orderByDesc('receipt_no');
    }

    public function messages(): array
    {
        return [
            'receive_date.required' => __('finishing.prod.v.receive_date'), 'batch_id.required' => __('finishing.prod.v.batch'), 'dia_type.required' => __('finishing.prod.v.dia'),
            'recv_qty_kg.required' => __('finishing.prod.v.recv_qty'), 'recv_qty_kg.gt' => __('finishing.prod.v.recv_qty'),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'receive_date' => [$required, 'date'],
            'batch_id' => [$required, $this->own('dye_batches')],
            'dia_type' => [$required, Rule::in(FinBatchReceipt::DIAS)],
            'booking_type' => ['nullable', Rule::in(FinBatchReceipt::BOOKINGS)],
            'challan_no' => ['nullable', 'string', 'max:40'],
            'challan_qty_kg' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'challan_rolls' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'recv_qty_kg' => [$required, 'numeric', 'gt:0', 'max:99999999'],
            'recv_rolls' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'floor_id' => ['nullable', $this->own('floors')],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if ($row && $row->getAttribute('status') === 'RETURNED') {
            throw ValidationException::withMessages(['receipt_no' => __('finishing.prod.v.receipt_returned', ['no' => $row->getAttribute('receipt_no')])]);
        }
        $get = fn (string $k) => array_key_exists($k, $data) ? $data[$k] : $row?->getAttribute($k);
        $batchId = (string) $get('batch_id');
        $batch = FinFloor::batches([$batchId])[$batchId];
        $moved = ! $row || $row->getAttribute('batch_id') !== $batchId;
        if ($moved) {
            if ($row && FinProductionLine::query()->where('receipt_id', $row->getKey())->exists()) {
                throw ValidationException::withMessages(['batch_id' => __('finishing.prod.v.batch_fixed', ['no' => $row->getAttribute('receipt_no')])]);
            }
            if ($batch->status !== 'OPEN') {
                throw ValidationException::withMessages(['batch_id' => __('finishing.prod.v.batch_closed', ['batch' => $batch->batch_no])]);
            }
            if ($live = FinFloor::liveCard($batchId, $row?->getKey())) {
                throw ValidationException::withMessages(['batch_id' => __('finishing.prod.v.batch_on_floor', ['batch' => $batch->batch_no, 'no' => $live->receipt_no])]);
            }
        }
        $dyed = FinFloor::dyedOn($batchId);
        if ($dyed === null) {
            throw ValidationException::withMessages(['batch_id' => __('finishing.prod.v.batch_not_dyed', ['batch' => $batch->batch_no])]);
        }
        $date = substr((string) $get('receive_date'), 0, 10);
        if ($date < $dyed) {
            throw ValidationException::withMessages(['receive_date' => __('finishing.prod.v.before_dyed', ['batch' => $batch->batch_no, 'date' => $dyed])]);
        }
        if (array_key_exists('challan_no', $data) && $data['challan_no'] !== null) {
            $data['challan_no'] = mb_strtoupper(trim((string) $data['challan_no'])) ?: null;
        }
        if (! $get('booking_type')) {
            $data['booking_type'] = $batch->scope === 'SAMPLE' ? 'SAMPLE' : 'IN_HOUSE';
        }
        $data['recv_rolls'] = (int) ($get('recv_rolls') ?? 0);
        if (! $row) {
            $data['receipt_no'] = $this->number('FBR-', 'fin_batch_receipts', 'receipt_no', $date);
            $data['stage'] = 'AWAITING_LOAD';
            // Entered on the day, it is stamped now; entered later, it reached the floor on its day — before anything run on it.
            $data['stage_at'] = $date < $this->today() ? CarbonImmutable::parse($date, 'Asia/Dhaka')->utc() : now();
            $data['status'] = 'ON_FLOOR';
            $data['created_by'] = $this->actingUserId();
        } else {
            unset($data['receipt_no'], $data['stage'], $data['status']);
        }

        return $data;
    }

    public function afterSave(Model $row, bool $created): void
    {
        if ($created) {
            FinProcessDelivery::query()->create(['receipt_id' => $row->getKey(), 'from_stage' => null, 'to_stage' => 'AWAITING_LOAD', 'delivered_at' => $row->getAttribute('stage_at') ?? now(), 'source' => 'RECEIPT', 'created_by' => $this->actingUserId()]);
        }
    }

    public function beforeDelete(Model $row): void
    {
        $no = $row->getAttribute('receipt_no');
        if (FinProductionLine::query()->where('receipt_id', $row->getKey())->exists()) {
            throw ValidationException::withMessages(['receipt_no' => __('finishing.prod.v.receipt_has_production', ['no' => $no])]);
        }
        if (FinReprocessReturn::query()->where('receipt_id', $row->getKey())->exists() || FinProcessDelivery::query()->where('receipt_id', $row->getKey())->where('source', '!=', 'RECEIPT')->exists()) {
            throw ValidationException::withMessages(['receipt_no' => __('finishing.prod.v.receipt_moved', ['no' => $no])]);
        }
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $b = FinFloor::batches([$row->getAttribute('batch_id')])[$row->getAttribute('batch_id')] ?? null;
        $out['batch_no'] = $b?->batch_no;
        $out['buyer'] = $b?->buyer;
        $out['order_no'] = $b?->order_no;
        $out['style'] = $b?->style;
        $out['colour'] = $b?->colour;
        $out['floor'] = $row->getAttribute('floor_id') ? Floor::query()->whereKey($row->getAttribute('floor_id'))->value('name') : null;
        foreach (['challan_qty_kg', 'recv_qty_kg'] as $k) {
            $out[$k] = $this->kg($row->getAttribute($k));
        }
        $out['diff_kg'] = $row->getAttribute('challan_qty_kg') === null ? null : round((float) $row->getAttribute('recv_qty_kg') - (float) $row->getAttribute('challan_qty_kg'), 3);

        return $out;
    }
}
