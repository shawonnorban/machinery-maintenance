<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Actions;

use App\Modules\Approval\Actions\DecideApproval;
use App\Modules\Approval\Actions\RequestApproval;
use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Services\ItemLineResolver;
use App\Modules\Procurement\Models\PurchaseOrderLine;
use App\Modules\Procurement\Models\PurchaseRequisition;
use App\Modules\Procurement\Models\PurchaseRequisitionLine;
use App\Modules\Settings\Services\NumberSequenceGenerator;
use App\Modules\Tenancy\Models\Factory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The purchase requisition's life (Procurement spec §5).
 *
 * Draft → submit (approval, if the company has a chain) → convert into
 * purchase orders → done. Nothing here touches stock; a requisition is a
 * request, and the stock arrives with the goods receipt.
 */
class ManagePurchaseRequisition
{
    public function __construct(
        private readonly NumberSequenceGenerator $numbers,
        private readonly ItemLineResolver $lines,
        private readonly RequestApproval $approvals,
        private readonly DecideApproval $decisions,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $lines
     */
    public function create(Factory $factory, array $data, array $lines, ?string $userId = null): PurchaseRequisition
    {
        $resolved = $this->lines->resolve($lines, 'requested_qty');

        return DB::transaction(function () use ($factory, $data, $resolved, $userId): PurchaseRequisition {
            $requisition = PurchaseRequisition::create([
                'factory_id' => $factory->id,
                'store_id' => $data['store_id'] ?? null,
                'pr_no' => $this->numbers->next('PURCHASE_REQUISITION', $factory),
                'pr_date' => $data['pr_date'] ?? CarbonImmutable::today(),
                'pr_type' => $data['pr_type'] ?? 'MANUAL',
                'source_reference' => $data['source_reference'] ?? null,
                'priority' => $data['priority'] ?? 'NORMAL',
                'status' => 'DRAFT',
                'requested_by' => $userId,
                'currency' => $data['currency'] ?? 'BDT',
                'notes' => $data['notes'] ?? null,
            ]);

            $this->writeLines($requisition, $resolved);

            return $requisition->fresh();
        });
    }

    /**
     * A draft is replaced wholesale — header and lines — because a line
     * edited in place would keep its stock snapshot from an earlier day.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $lines
     */
    public function update(PurchaseRequisition $requisition, array $data, array $lines): PurchaseRequisition
    {
        $this->assertEditable($requisition);

        $resolved = $this->lines->resolve($lines, 'requested_qty');

        return DB::transaction(function () use ($requisition, $data, $resolved): PurchaseRequisition {
            $requisition->forceFill([
                'store_id' => $data['store_id'] ?? $requisition->store_id,
                'pr_date' => $data['pr_date'] ?? $requisition->pr_date,
                'pr_type' => $data['pr_type'] ?? $requisition->pr_type,
                'priority' => $data['priority'] ?? $requisition->priority,
                'currency' => $data['currency'] ?? $requisition->currency,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $requisition->notes,
            ])->save();

            $requisition->lines()->delete();
            $this->writeLines($requisition, $resolved);

            return $requisition->fresh();
        });
    }

    /**
     * Submits for approval. Every item is checked again: one retired while
     * the draft sat open blocks the submit rather than sailing through on a
     * snapshot (Master Data spec §2.10).
     */
    public function submit(PurchaseRequisition $requisition, ?string $userId = null): PurchaseRequisition
    {
        $this->assertTransition($requisition, 'PENDING_APPROVAL');

        $requisition->load('lines');

        if ($requisition->lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('procurement.needs_lines')]);
        }

        $this->lines->assertAllActive($requisition->lines);

        return DB::transaction(function () use ($requisition, $userId): PurchaseRequisition {
            $requisition->forceFill([
                'status' => 'PENDING_APPROVAL',
                'submitted_at' => CarbonImmutable::now(),
                'estimated_total' => $requisition->lines->reduce(
                    fn (string $sum, PurchaseRequisitionLine $line): string => bcadd($sum, $line->estimatedAmount(), 4),
                    '0.0000',
                ),
            ])->save();

            // No workflow, or no matching step: the engine tells the document
            // NOT_REQUIRED and it moves itself straight to APPROVED.
            $this->approvals->forEntity($requisition, $userId);

            return $requisition->fresh();
        });
    }

    public function cancel(PurchaseRequisition $requisition, ?string $reason = null, ?string $userId = null): PurchaseRequisition
    {
        $this->assertTransition($requisition, 'CANCELLED');

        return DB::transaction(function () use ($requisition, $reason, $userId): PurchaseRequisition {
            // A request still waiting on an approver is withdrawn with the
            // document; otherwise it sits on the approvals list for a
            // requisition nobody can act on.
            $pending = ApprovalRequest::where('entity_type', 'PURCHASE_REQUISITION')
                ->where('entity_id', $requisition->id)
                ->where('status', 'PENDING')
                ->first();

            if ($pending !== null) {
                $this->decisions->cancel($pending, $userId, $reason);
            }

            $requisition->forceFill([
                'status' => 'CANCELLED',
                'cancelled_at' => CarbonImmutable::now(),
                'notes' => $reason === null ? $requisition->notes : trim(($requisition->notes ?? '')."\n".$reason),
            ])->save();

            return $requisition->fresh();
        });
    }

    /**
     * Called by purchase order creation: how much of each line is now on
     * order, and whether that finishes the requisition.
     *
     * @param  array<string, string>  $orderedByLineId  line id → quantity just placed
     */
    public function markConverted(PurchaseRequisition $requisition, array $orderedByLineId): PurchaseRequisition
    {
        if (! $requisition->isConvertible()) {
            throw ValidationException::withMessages([
                'status' => __('procurement.pr_not_convertible', ['status' => $requisition->status]),
            ])->status(409);
        }

        $requisition->load('lines');

        foreach ($requisition->lines as $line) {
            $placed = $orderedByLineId[$line->id] ?? null;

            if ($placed === null) {
                continue;
            }

            $newOrdered = bcadd((string) $line->ordered_qty, $placed, 4);

            if (bccomp($newOrdered, (string) $line->requested_qty, 4) > 0) {
                throw ValidationException::withMessages([
                    'lines' => __('procurement.pr_line_over_ordered', [
                        'item' => $line->item_code, 'pending' => rtrim(rtrim($line->pendingQty(), '0'), '.'),
                    ]),
                ]);
            }

            $line->forceFill(['ordered_qty' => $newOrdered])->save();
        }

        $requisition->refresh();

        $allPlaced = $requisition->lines->every(
            fn (PurchaseRequisitionLine $line): bool => bccomp($line->pendingQty(), '0', 4) === 0,
        );

        $requisition->forceFill(['status' => $allPlaced ? 'FULLY_CONVERTED' : 'PARTIALLY_CONVERTED'])->save();

        return $requisition->fresh();
    }

    /**
     * Other open requisitions asking for the same items — a warning shown
     * on the document, never a block: two floors can legitimately need the
     * same dye in the same week.
     *
     * @return list<array{id: string, pr_no: string, status: string, items: list<string>}>
     */
    public function openDuplicates(PurchaseRequisition $requisition): array
    {
        $itemIds = $requisition->lines()->pluck('item_id')->unique();

        if ($itemIds->isEmpty()) {
            return [];
        }

        return PurchaseRequisition::query()
            ->where('id', '!=', $requisition->id)
            ->where('factory_id', $requisition->factory_id)
            ->whereIn('status', ['DRAFT', 'PENDING_APPROVAL', 'APPROVED', 'PARTIALLY_CONVERTED'])
            ->whereHas('lines', fn ($q) => $q->whereIn('item_id', $itemIds))
            ->with(['lines' => fn ($q) => $q->whereIn('item_id', $itemIds)])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (PurchaseRequisition $other): array => [
                'id' => $other->id,
                'pr_no' => $other->pr_no,
                'status' => $other->status,
                'items' => $other->lines->pluck('item_code')->unique()->values()->all(),
            ])
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array{item: Item, uom_id: string, quantity: string, input: array<string, mixed>}>  $resolved
     */
    private function writeLines(PurchaseRequisition $requisition, $resolved): void
    {
        $itemIds = $resolved->pluck('item.id')->unique()->all();

        // Already on order elsewhere: open PO lines for the same items,
        // snapshotted so the approver sees what the requester saw.
        $onOrder = PurchaseOrderLine::query()
            ->whereIn('item_id', $itemIds)
            ->whereHas('order', fn ($q) => $q->whereIn('status', ['PENDING_APPROVAL', 'APPROVED', 'PARTIALLY_RECEIVED']))
            ->get()
            ->groupBy('item_id')
            ->map(fn ($lines) => $lines->reduce(
                fn (string $sum, PurchaseOrderLine $line): string => bcadd($sum, $line->pendingQty(), 4),
                '0.0000',
            ));

        foreach ($resolved as $line) {
            /** @var Item $item */
            $item = $line['item'];
            $input = $line['input'];

            PurchaseRequisitionLine::create([
                'purchase_requisition_id' => $requisition->id,
                'item_id' => $item->id,
                'item_code' => $item->item_code,
                'item_name' => $item->name,
                'uom_id' => $line['uom_id'],
                'requested_qty' => $line['quantity'],
                'required_date' => $input['required_date'] ?? null,
                'estimated_rate' => isset($input['estimated_rate']) && $input['estimated_rate'] !== '' && $input['estimated_rate'] !== null
                    ? ItemLineResolver::money($input['estimated_rate'])
                    : null,
                'current_stock_qty' => $item->totalOnHand(),
                'already_ordered_qty' => $onOrder->get($item->id, '0.0000'),
                'notes' => $input['notes'] ?? null,
            ]);
        }
    }

    private function assertEditable(PurchaseRequisition $requisition): void
    {
        if (! $requisition->isEditable()) {
            throw ValidationException::withMessages([
                'status' => __('procurement.only_draft_editable', ['status' => $requisition->status]),
            ])->status(409);
        }
    }

    private function assertTransition(PurchaseRequisition $requisition, string $to): void
    {
        if (! $requisition->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => __('procurement.invalid_transition', ['from' => $requisition->status, 'to' => $to]),
            ])->status(409);
        }
    }
}
