<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers\Api;

use App\Modules\Inventory\Actions\TransferStock;
use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\InventoryTransfer;
use App\Modules\Inventory\Models\InventoryTransferItem;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stock moving between factories, over the wire (API 14; mirrors the web
 * `TransferController`, which this delegates every write to unchanged per
 * ADR-003).
 *
 * Requested, approved, dispatched, received — four steps with four different
 * people behind them, and stock sits in an in-transit bin between dispatch
 * and receipt so a valuation taken mid-transit still balances.
 */
class InventoryTransferApiController extends ApiController
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly TransferStock $transfers,
    ) {}

    /**
     * The factory and bin dropdowns the "request a transfer" form needs.
     * Bins carry their `factory_id` here (unlike `SparePartApiController::
     * bins()`, which doesn't need it), so the from-bin picker can be
     * narrowed to whichever factory is selected. Gated on `inventory.
     * transfer.create` rather than `settings.factory.manage`: STORE_MANAGER
     * holds the former without the latter (`RoleSeeder`'s `$storeManager`
     * never merges from `$factoryManager`, where factory management is
     * granted) — the same gap Teams' own `formOptions()` closed for the
     * factory picker there.
     */
    public function formOptions(): JsonResponse
    {
        $this->allow('inventory.transfer.create');

        $factoryIds = $this->context->accessibleFactoryIds();

        return ApiResponse::ok([
            'factories' => Factory::whereIn('id', $factoryIds)->orderBy('name')->get(['id', 'name'])->all(),
            'bins' => Bin::where('active', true)
                ->with('store.warehouse')
                ->get()
                ->filter(fn (Bin $bin) => in_array($bin->factoryId(), $factoryIds, true))
                ->map(fn (Bin $bin): array => [
                    'id' => $bin->id,
                    'full_path' => $bin->fullPath(),
                    'factory_id' => $bin->factoryId(),
                ])->values()->all(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->allow('inventory.transfer.create');

        $factoryIds = $this->context->accessibleFactoryIds();

        $query = InventoryTransfer::query()
            ->with(['fromFactory:id,name', 'toFactory:id,name', 'items.sparePart:id,part_number,name'])
            // Either end of the move: the sending factory has to see it
            // leave and the receiving one has to see it coming.
            ->where(fn ($q) => $q->whereIn('from_factory_id', $factoryIds)->orWhereIn('to_factory_id', $factoryIds))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (InventoryTransfer $transfer): array => $this->summary($transfer),
        );
    }

    public function show(InventoryTransfer $transfer): JsonResponse
    {
        $this->allow('inventory.transfer.create');
        $this->assertEitherSide($transfer);

        $transfer->load(['fromFactory:id,name', 'toFactory:id,name', 'items.sparePart:id,part_number,name']);

        return ApiResponse::ok($this->detail($transfer));
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('inventory.transfer.create');

        $data = $request->validate([
            'from_factory_id' => ['required', 'string', 'size:26'],
            'to_factory_id' => ['required', 'string', 'size:26', 'different:from_factory_id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.spare_part_id' => ['required', 'string', 'size:26'],
            'items.*.from_bin_id' => ['required', 'string', 'size:26'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        if (! $this->context->canAccessFactory($data['from_factory_id'])) {
            abort(403);
        }

        $transfer = $this->transfers->request(
            Factory::findOrFail($data['from_factory_id']),
            // The receiving factory is not checked against what this caller
            // can reach: sending stock to a plant you do not administer is
            // the ordinary case, and the other end still has to accept it.
            Factory::findOrFail($data['to_factory_id']),
            $data['items'],
            $this->caller()->auditUserId(),
            $data['notes'] ?? null,
        );

        return ApiResponse::created($this->detail($transfer));
    }

    public function approve(InventoryTransfer $transfer): JsonResponse
    {
        $this->allow('inventory.transfer.approve');
        $this->assertSendingSide($transfer);

        return ApiResponse::ok($this->detail($this->transfers->approve($transfer, $this->caller()->auditUserId())));
    }

    public function reject(Request $request, InventoryTransfer $transfer): JsonResponse
    {
        $this->allow('inventory.transfer.approve');
        $this->assertSendingSide($transfer);

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return ApiResponse::ok($this->detail(
            $this->transfers->reject($transfer, $data['reason'], $this->caller()->auditUserId()),
        ));
    }

    public function dispatch(Request $request, InventoryTransfer $transfer): JsonResponse
    {
        $this->allow('inventory.transfer.dispatch');
        $this->assertSendingSide($transfer);

        $data = $request->validate([
            'quantities' => ['nullable', 'array'],
            'quantities.*' => ['numeric', 'gte:0'],
        ]);

        return ApiResponse::ok($this->detail(
            $this->transfers->dispatch($transfer, $data['quantities'] ?? [], $this->caller()->auditUserId()),
        ));
    }

    public function receive(Request $request, InventoryTransfer $transfer): JsonResponse
    {
        $this->allow('inventory.transfer.receive');
        $this->assertReceivingSide($transfer);

        $data = $request->validate([
            'quantities' => ['nullable', 'array'],
            'quantities.*' => ['numeric', 'gte:0'],
            'bins' => ['nullable', 'array'],
            'bins.*' => ['nullable', 'string', 'size:26'],
        ]);

        return ApiResponse::ok($this->detail($this->transfers->receive(
            $transfer,
            $data['quantities'] ?? [],
            array_filter($data['bins'] ?? []),
            $this->caller()->auditUserId(),
        )));
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(InventoryTransfer $transfer): array
    {
        return [
            'id' => $transfer->id,
            'transfer_number' => $transfer->transfer_number,
            'status' => $transfer->status,
            'from_factory' => $transfer->fromFactory === null ? null : [
                'id' => $transfer->fromFactory->id, 'name' => $transfer->fromFactory->name,
            ],
            'to_factory' => $transfer->toFactory === null ? null : [
                'id' => $transfer->toFactory->id, 'name' => $transfer->toFactory->name,
            ],
            'item_count' => $transfer->relationLoaded('items') ? $transfer->items->count() : null,
            'created_at' => $transfer->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(InventoryTransfer $transfer): array
    {
        $transfer->loadMissing(['fromFactory:id,name', 'toFactory:id,name', 'items.sparePart:id,part_number,name']);

        $sendingSide = $this->context->canAccessFactory((string) $transfer->from_factory_id);
        $receivingSide = $this->context->canAccessFactory((string) $transfer->to_factory_id);

        return $this->summary($transfer) + [
            // The web decides which action buttons to render by checking
            // `@can(...)` against the same side/status combination
            // `assertSendingSide()`/`assertReceivingSide()` enforce on
            // write; a client has no such local check, so it travels here
            // instead — same reasoning as Approval's `can_act`.
            'can_approve' => $transfer->status === 'REQUESTED' && $sendingSide && $this->caller()->can('inventory.transfer.approve'),
            'can_reject' => $transfer->status === 'REQUESTED' && $sendingSide && $this->caller()->can('inventory.transfer.approve'),
            'can_dispatch' => $transfer->status === 'APPROVED' && $sendingSide && $this->caller()->can('inventory.transfer.dispatch'),
            'can_receive' => $transfer->status === 'IN_TRANSIT' && $receivingSide && $this->caller()->can('inventory.transfer.receive'),
            'notes' => $transfer->notes,
            'requested_by' => $transfer->requested_by,
            'approved_by' => $transfer->approved_by,
            'approved_at' => $transfer->approved_at?->toIso8601String(),
            'dispatched_by' => $transfer->dispatched_by,
            'dispatched_at' => $transfer->dispatched_at?->toIso8601String(),
            'received_by' => $transfer->received_by,
            'received_at' => $transfer->received_at?->toIso8601String(),
            'rejected_by' => $transfer->rejected_by,
            'rejected_at' => $transfer->rejected_at?->toIso8601String(),
            'items' => $transfer->items->map(fn (InventoryTransferItem $item): array => [
                'id' => $item->id,
                'spare_part' => $item->sparePart === null ? null : [
                    'id' => $item->sparePart->id, 'part_number' => $item->sparePart->part_number, 'name' => $item->sparePart->name,
                ],
                'from_bin_id' => $item->from_bin_id,
                'to_bin_id' => $item->to_bin_id,
                'quantity_requested' => $item->quantity_requested,
                'quantity_dispatched' => $item->quantity_dispatched,
                'quantity_received' => $item->quantity_received,
                'quantity_variance' => $item->quantity_variance,
            ])->all(),
        ];
    }

    private function assertSendingSide(InventoryTransfer $transfer): void
    {
        if (! $this->context->canAccessFactory((string) $transfer->from_factory_id)) {
            abort(403);
        }
    }

    /**
     * Receiving belongs to the factory it is arriving at. A sending
     * storekeeper confirming receipt on the other end's behalf is how stock
     * gets marked as arrived while it is still on the van.
     */
    private function assertReceivingSide(InventoryTransfer $transfer): void
    {
        if (! $this->context->canAccessFactory((string) $transfer->to_factory_id)) {
            abort(403);
        }
    }

    private function assertEitherSide(InventoryTransfer $transfer): void
    {
        if (! $this->context->canAccessFactory((string) $transfer->from_factory_id)
            && ! $this->context->canAccessFactory((string) $transfer->to_factory_id)) {
            abort(404);
        }
    }
}
