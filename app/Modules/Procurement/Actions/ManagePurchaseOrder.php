<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Actions;

use App\Modules\Approval\Actions\DecideApproval;
use App\Modules\Approval\Actions\RequestApproval;
use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Services\ItemLineResolver;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\PurchaseOrderLine;
use App\Modules\Procurement\Models\PurchaseOrderRevision;
use App\Modules\Procurement\Models\PurchaseRequisition;
use App\Modules\Procurement\Services\RateContractResolver;
use App\Modules\Procurement\Models\PurchaseRequisitionLine;
use App\Modules\Settings\Services\NumberSequenceGenerator;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Vendor\Models\Vendor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The purchase order's life, local flow (Procurement spec §7).
 *
 * Raised from an approved requisition or directly, approved, received
 * against, closed. An approved order is never edited in place: a revision
 * snapshots the document as it stood, and a revision that costs more than
 * was signed for goes back for signature.
 */
class ManagePurchaseOrder
{
    public function __construct(
        private readonly NumberSequenceGenerator $numbers,
        private readonly ItemLineResolver $lines,
        private readonly RequestApproval $approvals,
        private readonly DecideApproval $decisions,
        private readonly ManagePurchaseRequisition $requisitions,
        private readonly RateContractResolver $rateContracts,
    ) {}

    /**
     * A direct order, no requisition behind it.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $lines  item_id, ordered_qty, rate, uom_id?, required_date?, notes?
     */
    public function create(Factory $factory, Vendor $vendor, array $data, array $lines, ?string $userId = null): PurchaseOrder
    {
        $this->assertVendorCanSupply($vendor);

        $resolved = $this->lines->resolve($lines, 'ordered_qty');

        return DB::transaction(function () use ($factory, $vendor, $data, $resolved, $userId): PurchaseOrder {
            $order = $this->newHeader($factory, $vendor, $data, $userId);
            $this->writeLines($order, $resolved);
            $this->recalculate($order);

            return $order->fresh();
        });
    }

    /**
     * An order for some or all of an approved requisition's outstanding
     * quantity. Lines name the requisition line they draw on, so the
     * requisition can tell how much of it is now on order.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array{pr_line_id: string, ordered_qty: string, rate: string, notes?: string}>  $lines
     */
    public function createFromRequisition(PurchaseRequisition $requisition, Vendor $vendor, array $data, array $lines, ?string $userId = null): PurchaseOrder
    {
        $this->assertVendorCanSupply($vendor);

        if (! $requisition->isConvertible()) {
            throw ValidationException::withMessages([
                'status' => __('procurement.pr_not_convertible', ['status' => $requisition->status]),
            ])->status(409);
        }

        $prLines = $requisition->lines()->get()->keyBy('id');

        $picked = collect($lines)->values()->map(function (array $line, int $index) use ($prLines): array {
            /** @var PurchaseRequisitionLine|null $prLine */
            $prLine = $prLines->get((string) ($line['pr_line_id'] ?? ''));

            if ($prLine === null) {
                throw ValidationException::withMessages(["lines.$index.pr_line_id" => __('procurement.unknown_pr_line')]);
            }

            $quantity = ItemLineResolver::money($line['ordered_qty'] ?? $prLine->pendingQty());

            if (bccomp($quantity, '0', 4) !== 1) {
                throw ValidationException::withMessages(["lines.$index.ordered_qty" => __('inventory.quantity_must_be_positive')]);
            }

            if (bccomp($quantity, $prLine->pendingQty(), 4) > 0) {
                throw ValidationException::withMessages(["lines.$index.ordered_qty" => __('procurement.pr_line_over_ordered', [
                    'item' => $prLine->item_code, 'pending' => rtrim(rtrim($prLine->pendingQty(), '0'), '.'),
                ])]);
            }

            return [
                'item_id' => $prLine->item_id,
                'uom_id' => $prLine->uom_id,
                'ordered_qty' => $quantity,
                'rate' => $line['rate'] ?? $prLine->estimated_rate ?? '0',
                'required_date' => $line['required_date'] ?? $prLine->required_date?->toDateString(),
                'notes' => $line['notes'] ?? null,
                'pr_line_id' => $prLine->id,
            ];
        });

        if ($picked->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('procurement.needs_lines')]);
        }

        $resolved = $this->lines->resolve($picked->all(), 'ordered_qty');

        $factory = Factory::findOrFail($requisition->factory_id);

        return DB::transaction(function () use ($factory, $vendor, $data, $resolved, $userId, $requisition, $picked): PurchaseOrder {
            $order = $this->newHeader($factory, $vendor, $data + [
                'delivery_store_id' => $data['delivery_store_id'] ?? $requisition->store_id,
                'currency' => $data['currency'] ?? $requisition->currency,
            ], $userId);

            $this->writeLines($order, $resolved);
            $this->recalculate($order);

            $this->requisitions->markConverted(
                $requisition,
                $picked->mapWithKeys(fn (array $line): array => [$line['pr_line_id'] => $line['ordered_qty']])->all(),
            );

            return $order->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $lines
     */
    public function update(PurchaseOrder $order, array $data, array $lines): PurchaseOrder
    {
        if (! $order->isEditable()) {
            throw ValidationException::withMessages([
                'status' => __('procurement.only_draft_editable', ['status' => $order->status]),
            ])->status(409);
        }


        $resolved = $this->lines->resolve($lines, 'ordered_qty');

        return DB::transaction(function () use ($order, $data, $resolved): PurchaseOrder {
            $this->applyHeader($order, $data);
            $order->lines()->delete();
            $this->writeLines($order, $resolved);
            $this->recalculate($order);

            return $order->fresh();
        });
    }

    public function submit(PurchaseOrder $order, ?string $userId = null): PurchaseOrder
    {
        $this->assertTransition($order, 'PENDING_APPROVAL');

        $order->load('lines');

        if ($order->lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('procurement.needs_lines')]);
        }

        $this->lines->assertAllActive($order->lines);

        return DB::transaction(function () use ($order, $userId): PurchaseOrder {
            $this->recalculate($order);

            $order->forceFill([
                'status' => 'PENDING_APPROVAL',
                'submitted_at' => CarbonImmutable::now(),
            ])->save();

            $this->approvals->forEntity($order, $userId);

            return $order->fresh();
        });
    }

    /**
     * Changes an approved order. The document as it stood is written to
     * the revision history first; then the header and lines are replaced.
     * A revision that grows past the approved subtotal goes back through
     * approval, since nobody signed for the larger figure.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $lines
     */
    public function revise(PurchaseOrder $order, string $reason, array $data, array $lines, ?string $userId = null): PurchaseOrder
    {
        if (! in_array($order->status, ['APPROVED', 'PARTIALLY_RECEIVED'], true)) {
            throw ValidationException::withMessages([
                'status' => __('procurement.po_not_revisable', ['status' => $order->status]),
            ])->status(409);
        }

        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => __('procurement.revision_needs_reason')]);
        }


        $resolved = $this->lines->resolve($lines, 'ordered_qty');

        return DB::transaction(function () use ($order, $reason, $data, $resolved, $userId): PurchaseOrder {
            $order->load('lines.uom');

            // A line already partly received cannot be cut below what came
            // in: the stock is on the shelf whether the order says so or not.
            $receivedByItem = $order->lines
                ->filter(fn (PurchaseOrderLine $line): bool => bccomp((string) $line->received_qty, '0', 4) > 0)
                ->mapWithKeys(fn (PurchaseOrderLine $line): array => [$line->item_id => (string) $line->received_qty]);

            foreach ($receivedByItem as $itemId => $received) {
                $kept = $resolved->firstWhere(fn (array $line): bool => $line['item']->id === $itemId);

                if ($kept === null || bccomp($kept['quantity'], $received, 4) < 0) {
                    throw ValidationException::withMessages([
                        'lines' => __('procurement.po_line_below_received', ['item' => $order->lines->firstWhere('item_id', $itemId)?->item_code]),
                    ]);
                }
            }

            $nextRevision = $order->revision_no + 1;

            PurchaseOrderRevision::create([
                'purchase_order_id' => $order->id,
                'revision_no' => $nextRevision,
                'reason' => $reason,
                'snapshot_json' => $this->snapshot($order),
                'revised_by' => $userId,
                'revised_at' => CarbonImmutable::now(),
            ]);

            $previousReceived = $order->lines->keyBy('item_id');

            $this->applyHeader($order, $data);
            $order->forceFill(['revision_no' => $nextRevision])->save();
            $order->lines()->delete();
            $this->writeLines($order, $resolved, $previousReceived);
            $this->recalculate($order);

            $approvedFor = (string) ($order->approved_subtotal ?? '0');

            if (bccomp((string) $order->fresh()->subtotal, $approvedFor, 4) > 0) {
                $this->cancelOpenRequest($order, $userId);

                $order->forceFill(['status' => 'PENDING_APPROVAL', 'submitted_at' => CarbonImmutable::now()])->save();
                $this->approvals->forEntity($order->fresh(), $userId);
            }

            return $order->fresh();
        });
    }

    public function cancel(PurchaseOrder $order, ?string $reason = null, ?string $userId = null): PurchaseOrder
    {
        $this->assertTransition($order, 'CANCELLED');

        return DB::transaction(function () use ($order, $reason, $userId): PurchaseOrder {
            $this->cancelOpenRequest($order, $userId);

            $order->forceFill([
                'status' => 'CANCELLED',
                'cancelled_at' => CarbonImmutable::now(),
                'notes' => $reason === null ? $order->notes : trim(($order->notes ?? '')."\n".$reason),
            ])->save();

            return $order->fresh();
        });
    }

    /** Closes an order the supplier will not deliver the rest of. */
    public function close(PurchaseOrder $order, ?string $userId = null): PurchaseOrder
    {
        $this->assertTransition($order, 'CLOSED');

        $order->forceFill(['status' => 'CLOSED', 'closed_at' => CarbonImmutable::now()])->save();

        return $order->fresh();
    }

    /**
     * Called by goods receipt completion, inside its transaction: what has
     * now arrived on this line, and whether that finishes the order.
     */
    public function recordReceipt(PurchaseOrderLine $line, string $quantity): void
    {
        $line->forceFill(['received_qty' => bcadd((string) $line->received_qty, $quantity, 4)])->save();

        $order = PurchaseOrder::findOrFail($line->purchase_order_id);
        $order->load('lines');

        $allReceived = $order->lines->every(
            fn (PurchaseOrderLine $l): bool => bccomp($l->pendingQty(), '0', 4) === 0,
        );

        $target = $allReceived ? 'RECEIVED' : 'PARTIALLY_RECEIVED';

        if ($order->status !== $target && $order->canTransitionTo($target)) {
            $order->forceFill(['status' => $target])->save();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function newHeader(Factory $factory, Vendor $vendor, array $data, ?string $userId): PurchaseOrder
    {
        $order = new PurchaseOrder([
            'factory_id' => $factory->id,
            'vendor_id' => $vendor->id,
            'po_no' => $this->numbers->next('PURCHASE_ORDER', $factory),
            'po_date' => $data['po_date'] ?? CarbonImmutable::today(),
            'purchase_type' => $this->purchaseType($data['purchase_type'] ?? 'LOCAL'),
            // Only an import can sit against a bond; a local purchase
            // carries no exemption to claim.
            'bond_applicable' => (bool) ($data['bond_applicable'] ?? false),
            'bond_licence_id' => $data['bond_licence_id'] ?? null,
            'duty_exemption_reference' => $data['duty_exemption_reference'] ?? null,
            'status' => 'DRAFT',
            'created_by' => $userId,
            'currency' => strtoupper((string) ($data['currency'] ?? $vendor->default_currency ?? 'BDT')),
            'payment_type_id' => $data['payment_type_id'] ?? $vendor->default_payment_type_id,
            'payment_terms' => $data['payment_terms'] ?? $vendor->default_payment_terms ?? null,
            'delivery_date' => $data['delivery_date'] ?? null,
            'delivery_store_id' => $data['delivery_store_id'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
        $order->save();

        return $order;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyHeader(PurchaseOrder $order, array $data): void
    {
        $order->forceFill([
            'po_date' => $data['po_date'] ?? $order->po_date,
            'purchase_type' => $this->purchaseType($data['purchase_type'] ?? $order->purchase_type),
            'bond_applicable' => array_key_exists('bond_applicable', $data)
                ? (bool) $data['bond_applicable']
                : $order->bond_applicable,
            'bond_licence_id' => array_key_exists('bond_licence_id', $data)
                ? $data['bond_licence_id']
                : $order->bond_licence_id,
            'duty_exemption_reference' => array_key_exists('duty_exemption_reference', $data)
                ? $data['duty_exemption_reference']
                : $order->duty_exemption_reference,
            'currency' => strtoupper((string) ($data['currency'] ?? $order->currency)),
            'payment_type_id' => array_key_exists('payment_type_id', $data) ? $data['payment_type_id'] : $order->payment_type_id,
            'payment_terms' => array_key_exists('payment_terms', $data) ? $data['payment_terms'] : $order->payment_terms,
            'delivery_date' => array_key_exists('delivery_date', $data) ? $data['delivery_date'] : $order->delivery_date,
            'delivery_store_id' => array_key_exists('delivery_store_id', $data) ? $data['delivery_store_id'] : $order->delivery_store_id,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $order->notes,
        ])->save();
    }

    /**
     * @param  Collection<int, array{item: Item, uom_id: string, quantity: string, input: array<string, mixed>}>  $resolved
     * @param  Collection<string, PurchaseOrderLine>|null  $previous  lines being replaced, keyed by item, to carry received quantities across a revision
     */
    private function writeLines(PurchaseOrder $order, Collection $resolved, ?Collection $previous = null): void
    {
        $vendor = Vendor::find($order->vendor_id);

        foreach ($resolved as $line) {
            /** @var Item $item */
            $item = $line['item'];
            $input = $line['input'];

            /*
             * A line that names a price keeps it. The contract is consulted
             * only when none was given (Procurement spec §14): a buyer who
             * types a rate is making a decision, and silently overwriting it
             * would take that decision away and leave no trace of either
             * number.
             */
            $agreed = array_key_exists('rate', $input) || $vendor === null
                ? null
                : $this->rateContracts->resolve(
                    $vendor,
                    $item,
                    $line['quantity'],
                    (string) $order->factory_id,
                    CarbonImmutable::parse((string) ($order->po_date ?? CarbonImmutable::today())),
                );

            $rate = $agreed !== null
                ? $agreed['rate']
                : ItemLineResolver::money($input['rate'] ?? '0');

            PurchaseOrderLine::create([
                'purchase_order_id' => $order->id,
                'pr_line_id' => $input['pr_line_id'] ?? $previous?->get($item->id)?->pr_line_id,
                'item_id' => $item->id,
                'item_code' => $item->item_code,
                'item_name' => $item->name,
                'uom_id' => $line['uom_id'],
                'ordered_qty' => $line['quantity'],
                'rate' => $rate,
                // Which agreement the price came from, so "why was this
                // 190?" has an answer six months later.
                'rate_contract_id' => $agreed['contract']->id ?? null,
                'amount' => bcmul($line['quantity'], $rate, 4),
                'received_qty' => (string) ($previous?->get($item->id)?->received_qty ?? '0'),
                'required_date' => $input['required_date'] ?? null,
                'technical_spec_json' => $input['technical_spec'] ?? null,
                'notes' => $input['notes'] ?? null,
            ]);
        }
    }

    private function recalculate(PurchaseOrder $order): void
    {
        $subtotal = $order->lines()->get()->reduce(
            fn (string $sum, PurchaseOrderLine $line): string => bcadd($sum, (string) $line->amount, 4),
            '0.0000',
        );

        $order->forceFill(['subtotal' => $subtotal])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(PurchaseOrder $order): array
    {
        return [
            'revision_no' => $order->revision_no,
            'po_date' => $order->po_date?->toDateString(),
            'currency' => $order->currency,
            'payment_type_id' => $order->payment_type_id,
            'payment_terms' => $order->payment_terms,
            'delivery_date' => $order->delivery_date?->toDateString(),
            'delivery_store_id' => $order->delivery_store_id,
            'subtotal' => (string) $order->subtotal,
            'notes' => $order->notes,
            'lines' => $order->lines->map(fn (PurchaseOrderLine $line): array => [
                'item_id' => $line->item_id,
                'item_code' => $line->item_code,
                'item_name' => $line->item_name,
                'uom_id' => $line->uom_id,
                'uom_code' => $line->relationLoaded('uom') ? $line->uom?->code : null,
                'ordered_qty' => (string) $line->ordered_qty,
                'rate' => (string) $line->rate,
                'amount' => (string) $line->amount,
                'received_qty' => (string) $line->received_qty,
                'required_date' => $line->required_date?->toDateString(),
                'notes' => $line->notes,
            ])->all(),
        ];
    }

    private function cancelOpenRequest(PurchaseOrder $order, ?string $userId): void
    {
        $pending = ApprovalRequest::where('entity_type', 'PURCHASE_ORDER')
            ->where('entity_id', $order->id)
            ->where('status', 'PENDING')
            ->first();

        if ($pending !== null) {
            $this->decisions->cancel($pending, $userId, __('procurement.superseded_by_revision'));
        }
    }

    private function assertVendorCanSupply(Vendor $vendor): void
    {
        if ($vendor->status !== 'ACTIVE' || ! $vendor->supplies()) {
            // A blacklisted or service-only vendor cannot be ordered from;
            // the form filters them out, and this catches a stale id.
            throw ValidationException::withMessages(['vendor_id' => __('procurement.vendor_cannot_supply')]);
        }
    }

    /**
     * LOCAL or IMPORT. An import order carries no extra fields of its own —
     * its proforma invoice, letter of credit and shipments are documents
     * hung off it (spec §10), so the type is a label on the order and the
     * paperwork lives in its own tables.
     */
    private function purchaseType(string $purchaseType): string
    {
        $type = strtoupper($purchaseType);

        if (! in_array($type, PurchaseOrder::PURCHASE_TYPES, true)) {
            throw ValidationException::withMessages([
                'purchase_type' => __('validation.in', ['attribute' => 'purchase_type']),
            ]);
        }

        return $type;
    }

    private function assertTransition(PurchaseOrder $order, string $to): void
    {
        if (! $order->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => __('procurement.invalid_transition', ['from' => $order->status, 'to' => $to]),
            ])->status(409);
        }
    }
}
