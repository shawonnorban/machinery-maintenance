<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers\Web;

use App\Modules\Inventory\Actions\TransferStock;
use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\InventoryTransfer;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Moving stock between factories (SRS 23.4).
 *
 * Four steps with four different people behind them: one factory asks, someone
 * approves, the sending store dispatches, the receiving store confirms. The
 * stock is not in either factory in between — it sits in an in-transit bin, so
 * a valuation taken while a van is on the road still balances.
 *
 * That is also why receiving is a separate step from dispatching. Marking a
 * transfer complete when the van leaves would show stock on a shelf it has not
 * reached, and the first stock count after that would disagree with the ledger.
 */
class TransferController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly TransferStock $transfers,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeTransfers($request, 'inventory.transfer.create');

        $factoryIds = $this->context->accessibleFactoryIds();

        $transfers = InventoryTransfer::query()
            ->with(['fromFactory:id,name', 'toFactory:id,name', 'items.sparePart:id,part_number,name'])
            // Either end of the move: the sending factory has to see it leave
            // and the receiving one has to see it coming.
            ->where(fn ($q) => $q->whereIn('from_factory_id', $factoryIds)
                ->orWhereIn('to_factory_id', $factoryIds))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(min(max((int) $request->query('per_page', 25), 10), 100))
            ->withQueryString();

        return view('inventory::transfers.index', [
            'transfers' => $transfers,
            'statuses' => InventoryTransfer::STATUSES,
            'factories' => Factory::whereIn('id', $factoryIds)->orderBy('name')->get(),
            'spareParts' => SparePart::where('active', true)->orderBy('part_number')->get(['id', 'part_number', 'name']),
            'bins' => Bin::where('is_in_transit', false)
                ->where('active', true)
                ->with('store.warehouse')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeTransfers($request, 'inventory.transfer.create');

        $data = $request->validate([
            'from_factory_id' => ['required', 'string', 'size:26'],
            'to_factory_id' => ['required', 'string', 'size:26', 'different:from_factory_id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.spare_part_id' => ['required', 'string', 'size:26'],
            'items.*.from_bin_id' => ['required', 'string', 'size:26'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $transfer = $this->transfers->request(
            $this->factory($data['from_factory_id']),
            // The receiving factory is not checked against what this user can
            // reach: sending stock to a plant you do not administer is the
            // ordinary case, and the other end still has to accept it.
            Factory::findOrFail($data['to_factory_id']),
            $data['items'],
            $request->user()->id,
            $data['notes'] ?? null,
        );

        return redirect()
            ->route('app.inventory.transfers')
            ->with('status', __('inventory.transfer_requested', ['number' => $transfer->transfer_number]));
    }

    public function approve(Request $request, InventoryTransfer $transfer): RedirectResponse
    {
        $this->authorizeTransfers($request, 'inventory.transfer.approve');
        $this->assertSendingSide($transfer);

        $this->transfers->approve($transfer, $request->user()->id);

        return back()->with('status', __('inventory.transfer_approved'));
    }

    public function reject(Request $request, InventoryTransfer $transfer): RedirectResponse
    {
        $this->authorizeTransfers($request, 'inventory.transfer.approve');
        $this->assertSendingSide($transfer);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->transfers->reject($transfer, $data['reason'], $request->user()->id);

        return back()->with('status', __('inventory.transfer_rejected'));
    }

    public function dispatch(Request $request, InventoryTransfer $transfer): RedirectResponse
    {
        $this->authorizeTransfers($request, 'inventory.transfer.dispatch');
        $this->assertSendingSide($transfer);

        $data = $request->validate([
            'quantities' => ['nullable', 'array'],
            'quantities.*' => ['numeric', 'gte:0'],
        ]);

        $this->transfers->dispatch($transfer, $data['quantities'] ?? [], $request->user()->id);

        return back()->with('status', __('inventory.transfer_dispatched'));
    }

    public function receive(Request $request, InventoryTransfer $transfer): RedirectResponse
    {
        $this->authorizeTransfers($request, 'inventory.transfer.receive');
        $this->assertReceivingSide($transfer);

        $data = $request->validate([
            'quantities' => ['nullable', 'array'],
            'quantities.*' => ['numeric', 'gte:0'],
            'bins' => ['nullable', 'array'],
            'bins.*' => ['nullable', 'string', 'size:26'],
        ]);

        $this->transfers->receive(
            $transfer,
            $data['quantities'] ?? [],
            array_filter($data['bins'] ?? []),
            $request->user()->id,
        );

        return back()->with('status', __('inventory.transfer_received'));
    }

    private function factory(string $id): Factory
    {
        if (! $this->context->canAccessFactory($id)) {
            abort(403);
        }

        return Factory::findOrFail($id);
    }

    /**
     * Approving and dispatching belong to the factory the stock is leaving.
     */
    private function assertSendingSide(InventoryTransfer $transfer): void
    {
        if (! $this->context->canAccessFactory((string) $transfer->from_factory_id)) {
            abort(403);
        }
    }

    /**
     * Receiving belongs to the factory it is arriving at. A sending storekeeper
     * confirming receipt on the other end's behalf is how stock gets marked as
     * arrived while it is still on the van.
     */
    private function assertReceivingSide(InventoryTransfer $transfer): void
    {
        if (! $this->context->canAccessFactory((string) $transfer->to_factory_id)) {
            abort(403);
        }
    }

    private function authorizeTransfers(Request $request, string $permission): void
    {
        if (! $request->user()->can($permission)) {
            abort(403);
        }
    }
}
