<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Actions;

use App\Modules\Finance\Services\GlPoster;
use App\Modules\ItemMaster\Services\ItemLineResolver;
use App\Modules\Procurement\Models\GoodsReceipt;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\SupplierAdvance;
use App\Modules\Procurement\Models\SupplierAdvanceAdjustment;
use App\Modules\Settings\Services\NumberSequenceGenerator;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Vendor\Models\Vendor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Money paid to a supplier before the goods arrive (Procurement spec §13).
 *
 * A mill will not start weaving on a promise. Until the bills come in, what
 * was paid up front is an asset owed back in goods, and the number the
 * finance office actually asks for is the outstanding one: how much of what
 * we have handed over is still unaccounted for.
 *
 * Two things are deliberate:
 *
 *  - **Adjustments are rows, not a decrement.** A balance that only goes
 *    down cannot say which bill consumed it, and an advance set against the
 *    wrong receipt has to be findable and reversible.
 *  - **Reversal, not deletion.** A reversed adjustment keeps its row with a
 *    timestamp, so "this advance was adjusted twice against the same GRN"
 *    stays answerable months later.
 */
class ManageSupplierAdvance
{
    private const SCALE = 4;

    public function __construct(
        private readonly NumberSequenceGenerator $numbers,
        private readonly GlPoster $gl,
    ) {}

    /**
     * @param  array<string, mixed>  $data  vendor_id, amount, advance_date?,
     *                                      purchase_order_id?, payment_type_id?, reference_no?, notes?
     */
    public function create(Factory $factory, array $data, ?string $userId = null): SupplierAdvance
    {
        $vendorId = $data['vendor_id'] ?? null;

        if ($vendorId === null || Vendor::where('status', 'ACTIVE')->find($vendorId) === null) {
            throw ValidationException::withMessages(['vendor_id' => __('itemmaster.unknown_vendor')]);
        }

        $amount = ItemLineResolver::money($data['amount'] ?? 0);

        if (bccomp($amount, '0', self::SCALE) !== 1) {
            throw ValidationException::withMessages(['amount' => __('inventory.quantity_must_be_positive')]);
        }

        $order = $this->orderFor($factory, $data['purchase_order_id'] ?? null, (string) $vendorId);

        $advance = SupplierAdvance::create([
            'factory_id' => $factory->id,
            'advance_no' => $this->numbers->next('SUPPLIER_ADVANCE', $factory),
            'advance_date' => $data['advance_date'] ?? CarbonImmutable::today(),
            'vendor_id' => $vendorId,
            'purchase_order_id' => $order?->id,
            'payment_type_id' => $data['payment_type_id'] ?? null,
            'reference_no' => $data['reference_no'] ?? null,
            'amount' => $amount,
            'adjusted_amount' => '0',
            'refunded_amount' => '0',
            'currency' => $data['currency'] ?? $order?->currency ?? 'BDT',
            'status' => 'OPEN',
            'notes' => $data['notes'] ?? null,
            'created_by' => $userId,
        ])->fresh();

        // Money out of the bank and owed back in goods (Shared Systems §2).
        $this->gl->post('ADVANCE_PAYMENT', (string) $advance->amount, [
            'source_type' => 'supplier_advance',
            'source_id' => $advance->id,
            'source_no' => $advance->advance_no,
            'factory_id' => $factory->id,
            'currency' => $advance->currency,
            'date' => $advance->advance_date,
            'memo' => $advance->advance_no,
            'user_id' => $userId,
        ]);

        return $advance;
    }

    /**
     * Sets part of the advance against a bill that has arrived.
     *
     * @param  array<string, mixed>  $data  amount, goods_receipt_id?, reference_no?, notes?
     */
    public function adjust(SupplierAdvance $advance, array $data, ?string $userId = null): SupplierAdvance
    {
        return $this->record($advance, $data, 'ADJUSTMENT', $userId);
    }

    /**
     * Money handed back rather than worked off — an order that never came.
     *
     * @param  array<string, mixed>  $data
     */
    public function refund(SupplierAdvance $advance, array $data, ?string $userId = null): SupplierAdvance
    {
        return $this->record($advance, $data, 'REFUND', $userId);
    }

    /**
     * Undoes one adjustment. The row stays, stamped, because an advance
     * adjusted twice against the same receipt is exactly the mistake this
     * has to be able to explain afterwards.
     */
    public function reverse(SupplierAdvanceAdjustment $adjustment, ?string $reason = null): SupplierAdvance
    {
        if (! $adjustment->isLive()) {
            throw ValidationException::withMessages([
                'adjustment_id' => __('procurement.adjustment_already_reversed'),
            ])->status(409);
        }

        return DB::transaction(function () use ($adjustment, $reason): SupplierAdvance {
            $advance = SupplierAdvance::lockForUpdate()->findOrFail($adjustment->supplier_advance_id);

            $column = $adjustment->adjustment_type === 'REFUND' ? 'refunded_amount' : 'adjusted_amount';
            $reduced = bcsub((string) $advance->{$column}, (string) $adjustment->amount, self::SCALE);

            $adjustment->forceFill([
                'reversed_at' => CarbonImmutable::now(),
                'notes' => $reason === null ? $adjustment->notes : trim(($adjustment->notes ?? '')."\n".$reason),
            ])->save();

            $advance->forceFill([
                $column => bccomp($reduced, '0', self::SCALE) === 1 ? $reduced : '0.0000',
            ])->save();

            return $this->settle($advance->fresh());
        });
    }

    public function cancel(SupplierAdvance $advance, ?string $reason = null): SupplierAdvance
    {
        if (bccomp((string) $advance->adjusted_amount, '0', self::SCALE) === 1
            || bccomp((string) $advance->refunded_amount, '0', self::SCALE) === 1) {
            // Part of it has already been used; cancelling would erase a
            // bill's settlement rather than the advance.
            throw ValidationException::withMessages([
                'status' => __('procurement.advance_already_used'),
            ])->status(409);
        }

        $advance->forceFill([
            'status' => 'CANCELLED',
            'cancelled_at' => CarbonImmutable::now(),
            'notes' => $reason === null ? $advance->notes : trim(($advance->notes ?? '')."\n".$reason),
        ])->save();

        return $advance->fresh();
    }

    /**
     * What a supplier is still holding of ours, across every open advance.
     *
     * @return array{advances: int, paid: string, adjusted: string, refunded: string, outstanding: string}
     */
    public function outstandingFor(string $vendorId): array
    {
        $advances = SupplierAdvance::where('vendor_id', $vendorId)
            ->whereIn('status', ['OPEN', 'PARTIALLY_ADJUSTED'])
            ->get();

        $paid = '0.0000';
        $adjusted = '0.0000';
        $refunded = '0.0000';
        $outstanding = '0.0000';

        foreach ($advances as $advance) {
            $paid = bcadd($paid, (string) $advance->amount, self::SCALE);
            $adjusted = bcadd($adjusted, (string) $advance->adjusted_amount, self::SCALE);
            $refunded = bcadd($refunded, (string) $advance->refunded_amount, self::SCALE);
            $outstanding = bcadd($outstanding, $advance->outstanding(), self::SCALE);
        }

        return [
            'advances' => $advances->count(),
            'paid' => $paid,
            'adjusted' => $adjusted,
            'refunded' => $refunded,
            'outstanding' => $outstanding,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function record(SupplierAdvance $advance, array $data, string $type, ?string $userId): SupplierAdvance
    {
        if (! $advance->isOpen()) {
            throw ValidationException::withMessages([
                'status' => __('procurement.advance_not_open', ['status' => $advance->status]),
            ])->status(409);
        }

        $amount = ItemLineResolver::money($data['amount'] ?? 0);

        if (bccomp($amount, '0', self::SCALE) !== 1) {
            throw ValidationException::withMessages(['amount' => __('inventory.quantity_must_be_positive')]);
        }

        if (bccomp($amount, $advance->outstanding(), self::SCALE) > 0) {
            throw ValidationException::withMessages([
                'amount' => __('procurement.adjustment_exceeds_advance', [
                    'outstanding' => rtrim(rtrim($advance->outstanding(), '0'), '.'),
                ]),
            ])->status(409);
        }

        $receipt = $this->receiptFor($advance, $data['goods_receipt_id'] ?? null);

        return DB::transaction(function () use ($advance, $amount, $type, $receipt, $data, $userId): SupplierAdvance {
            $adjustment = SupplierAdvanceAdjustment::create([
                'supplier_advance_id' => $advance->id,
                'goods_receipt_id' => $receipt?->id,
                'adjustment_type' => $type,
                'amount' => $amount,
                'reference_no' => $data['reference_no'] ?? null,
                'notes' => $data['notes'] ?? null,
                'adjusted_by' => $userId,
                'adjusted_at' => CarbonImmutable::now(),
            ]);

            $column = $type === 'REFUND' ? 'refunded_amount' : 'adjusted_amount';

            $advance->forceFill([
                $column => bcadd((string) $advance->{$column}, $amount, self::SCALE),
            ])->save();

            $settled = $this->settle($advance->fresh());

            // Only an adjustment is a settlement: a refund is the advance
            // coming back, which unwinds the original payment rather than
            // paying a bill with it.
            if ($type === 'ADJUSTMENT') {
                $this->gl->post('INVOICE_SETTLEMENT', $amount, [
                    'source_type' => 'supplier_advance_adjustment',
                    'source_id' => $adjustment->id,
                    'source_no' => $settled->advance_no,
                    'factory_id' => $settled->factory_id,
                    'currency' => $settled->currency,
                    'date' => $adjustment->adjusted_at,
                    'memo' => $receipt?->grn_no ?? $settled->advance_no,
                    'user_id' => $userId,
                    // The whole of this settlement came out of the advance;
                    // the bank leg is zero until an invoice entity exists to
                    // say what the rest of the bill was.
                    'from_advance' => $amount,
                ]);
            }

            return $settled;
        });
    }

    /** Where the advance stands once its rows have moved. */
    private function settle(SupplierAdvance $advance): SupplierAdvance
    {
        $used = bcadd((string) $advance->adjusted_amount, (string) $advance->refunded_amount, self::SCALE);

        $status = match (true) {
            bccomp($used, (string) $advance->amount, self::SCALE) >= 0 => bccomp((string) $advance->refunded_amount, '0', self::SCALE) === 1
                && bccomp((string) $advance->adjusted_amount, '0', self::SCALE) !== 1
                    ? 'REFUNDED'
                    : 'ADJUSTED',
            bccomp($used, '0', self::SCALE) === 1 => 'PARTIALLY_ADJUSTED',
            default => 'OPEN',
        };

        $advance->forceFill([
            'status' => $status,
            'closed_at' => in_array($status, ['ADJUSTED', 'REFUNDED'], true) ? CarbonImmutable::now() : null,
        ])->save();

        return $advance->fresh();
    }

    private function orderFor(Factory $factory, ?string $orderId, string $vendorId): ?PurchaseOrder
    {
        if ($orderId === null || $orderId === '') {
            return null;
        }

        $order = PurchaseOrder::where('factory_id', $factory->id)->find($orderId);

        if ($order === null) {
            throw ValidationException::withMessages(['purchase_order_id' => __('procurement.unknown_order')]);
        }

        if ((string) $order->vendor_id !== $vendorId) {
            // An advance against another supplier's order would never find
            // a bill to settle against.
            throw ValidationException::withMessages([
                'purchase_order_id' => __('procurement.advance_order_vendor_mismatch'),
            ]);
        }

        return $order;
    }

    private function receiptFor(SupplierAdvance $advance, ?string $receiptId): ?GoodsReceipt
    {
        if ($receiptId === null || $receiptId === '') {
            return null;
        }

        $receipt = GoodsReceipt::find($receiptId);

        if ($receipt === null) {
            throw ValidationException::withMessages(['goods_receipt_id' => __('procurement.unknown_receipt')]);
        }

        if ($receipt->vendor_id !== null && (string) $receipt->vendor_id !== (string) $advance->vendor_id) {
            throw ValidationException::withMessages([
                'goods_receipt_id' => __('procurement.advance_receipt_vendor_mismatch'),
            ]);
        }

        return $receipt;
    }
}
