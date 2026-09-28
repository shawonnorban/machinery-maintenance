<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Actions;

use App\Modules\ItemMaster\Models\FabricRoll;
use App\Modules\ItemMaster\Services\ItemLineResolver;
use App\Modules\Procurement\Models\GoodsReceipt;
use App\Modules\Procurement\Models\SupplierClaim;
use App\Modules\Settings\Services\NumberSequenceGenerator;
use App\Modules\Tenancy\Models\Factory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Claiming money from a supplier (Procurement spec §12).
 *
 * The counterpart to a return: when the goods cannot go back — already cut,
 * dyed, sewn or used — the loss is claimed in money instead. So **nothing
 * here touches stock**. The factory keeps what it has; what is in dispute
 * is a number.
 *
 * Settlement is recorded in instalments because that is how it happens. A
 * mill issues a credit note for part of it, argues about the rest, and the
 * claim closes when the two add up — or when somebody gives up and
 * withdraws it, which is also an answer and is worth recording as one.
 */
class ManageSupplierClaim
{
    private const SCALE = 4;

    public function __construct(private readonly NumberSequenceGenerator $numbers) {}

    /**
     * @param  array<string, mixed>  $data  vendor_id, reason, claim_amount, claim_date?,
     *                                      goods_receipt_id?, purchase_return_id?, fabric_roll_id?,
     *                                      description?, currency?
     */
    public function create(Factory $factory, array $data, ?string $userId = null): SupplierClaim
    {
        $reason = (string) ($data['reason'] ?? 'OTHER');

        if (! in_array($reason, SupplierClaim::REASONS, true)) {
            throw ValidationException::withMessages(['reason' => __('validation.in', ['attribute' => 'reason'])]);
        }

        $amount = ItemLineResolver::money($data['claim_amount'] ?? 0);

        if (bccomp($amount, '0', self::SCALE) !== 1) {
            throw ValidationException::withMessages(['claim_amount' => __('inventory.quantity_must_be_positive')]);
        }

        $receipt = $this->receiptFor($factory, $data['goods_receipt_id'] ?? null);
        $roll = $this->rollFor($data['fabric_roll_id'] ?? null);

        return SupplierClaim::create([
            'factory_id' => $factory->id,
            'claim_no' => $this->numbers->next('SUPPLIER_CLAIM', $factory),
            'claim_date' => $data['claim_date'] ?? CarbonImmutable::today(),
            'vendor_id' => $data['vendor_id'],
            'goods_receipt_id' => $receipt?->id,
            'purchase_return_id' => $data['purchase_return_id'] ?? null,
            'fabric_roll_id' => $roll?->id,
            'item_id' => $roll?->item_id ?? ($data['item_id'] ?? null),
            'reason' => $reason,
            'status' => 'RAISED',
            'claim_amount' => $amount,
            'settled_amount' => '0',
            'currency' => $data['currency'] ?? $receipt?->lines->first()?->currency ?? 'BDT',
            'description' => $data['description'] ?? null,
            'created_by' => $userId,
        ])->fresh();
    }

    /** The supplier agrees the claim stands, without paying yet. */
    public function accept(SupplierClaim $claim, ?string $reference = null): SupplierClaim
    {
        $this->assertTransition($claim, 'ACCEPTED');

        $claim->forceFill([
            'status' => 'ACCEPTED',
            'settlement_reference' => $reference ?? $claim->settlement_reference,
            'accepted_at' => CarbonImmutable::now(),
        ])->save();

        return $claim->fresh();
    }

    /**
     * Money actually recovered, in whatever instalments it arrives.
     *
     * @param  array<string, mixed>  $data  amount, reference?, notes?
     */
    public function settle(SupplierClaim $claim, array $data): SupplierClaim
    {
        if (! $claim->isOpen()) {
            throw ValidationException::withMessages([
                'status' => __('procurement.claim_not_open', ['status' => $claim->status]),
            ])->status(409);
        }

        $amount = ItemLineResolver::money($data['amount'] ?? 0);

        if (bccomp($amount, '0', self::SCALE) !== 1) {
            throw ValidationException::withMessages(['amount' => __('inventory.quantity_must_be_positive')]);
        }

        if (bccomp($amount, $claim->outstanding(), self::SCALE) > 0) {
            // Recovering more than was claimed is not generosity, it is a
            // typo — and it would make the recovery rate report nonsense.
            throw ValidationException::withMessages([
                'amount' => __('procurement.settlement_exceeds_claim', [
                    'outstanding' => rtrim(rtrim($claim->outstanding(), '0'), '.'),
                ]),
            ])->status(409);
        }

        return DB::transaction(function () use ($claim, $amount, $data): SupplierClaim {
            $settled = bcadd((string) $claim->settled_amount, $amount, self::SCALE);
            $closed = bccomp($settled, (string) $claim->claim_amount, self::SCALE) >= 0;

            $claim->forceFill([
                'settled_amount' => $settled,
                'status' => $closed ? 'SETTLED' : 'PARTIALLY_SETTLED',
                'settlement_reference' => $data['reference'] ?? $claim->settlement_reference,
                'settled_at' => $closed ? CarbonImmutable::now() : $claim->settled_at,
                'notes' => isset($data['notes'])
                    ? trim(($claim->notes ?? '')."\n".$data['notes'])
                    : $claim->notes,
            ])->save();

            return $claim->fresh();
        });
    }

    public function reject(SupplierClaim $claim, string $reason): SupplierClaim
    {
        $this->assertTransition($claim, 'REJECTED');

        $claim->forceFill([
            'status' => 'REJECTED',
            'rejected_at' => CarbonImmutable::now(),
            'notes' => trim(($claim->notes ?? '')."\n".$reason),
        ])->save();

        return $claim->fresh();
    }

    /** Given up on — recorded, because a claim that simply vanishes is a claim nobody learns from. */
    public function withdraw(SupplierClaim $claim, ?string $reason = null): SupplierClaim
    {
        $this->assertTransition($claim, 'WITHDRAWN');

        $claim->forceFill([
            'status' => 'WITHDRAWN',
            'withdrawn_at' => CarbonImmutable::now(),
            'notes' => $reason === null ? $claim->notes : trim(($claim->notes ?? '')."\n".$reason),
        ])->save();

        return $claim->fresh();
    }

    private function receiptFor(Factory $factory, ?string $receiptId): ?GoodsReceipt
    {
        if ($receiptId === null || $receiptId === '') {
            return null;
        }

        $receipt = GoodsReceipt::where('factory_id', $factory->id)->find($receiptId);

        if ($receipt === null) {
            throw ValidationException::withMessages(['goods_receipt_id' => __('procurement.unknown_receipt')]);
        }

        return $receipt;
    }

    private function rollFor(?string $rollId): ?FabricRoll
    {
        if ($rollId === null || $rollId === '') {
            return null;
        }

        $roll = FabricRoll::find($rollId);

        if ($roll === null) {
            throw ValidationException::withMessages(['fabric_roll_id' => __('requisition.unknown_roll')]);
        }

        return $roll;
    }

    private function assertTransition(SupplierClaim $claim, string $to): void
    {
        if (! $claim->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => __('procurement.invalid_transition', ['from' => $claim->status, 'to' => $to]),
            ])->status(409);
        }
    }
}
