<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers\Web;

use App\Modules\Inventory\Actions\ReceiveStock;
use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\InventoryBalance;
use App\Modules\Inventory\Models\InventoryTransaction;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Services\InventoryLedger;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly ReceiveStock $receive,
        private readonly InventoryLedger $ledger,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('inventory.stock.view');

        $balances = InventoryBalance::query()
            ->with(['sparePart:id,part_number,name,unit,minimum_stock,reorder_level', 'bin.store.warehouse'])
            ->whereHas('bin', fn ($q) => $q->where('is_in_transit', false))
            ->when(filled($request->query('search')), function ($q) use ($request): void {
                $term = '%'.$request->query('search').'%';
                $q->whereHas('sparePart', fn ($p) => $p->where('part_number', 'like', $term)
                    ->orWhere('name', 'like', $term));
            })
            ->when(filled($request->query('bin_id')), fn ($q) => $q->where('bin_id', $request->query('bin_id')))
            ->orderByDesc('quantity_on_hand')
            ->paginate(min(max((int) $request->query('per_page', 25), 10), 100))
            ->withQueryString();

        return view('inventory::stock.index', [
            'balances' => $balances,
            'bins' => $this->accessibleBins(),
            'totals' => $this->totals(),
        ]);
    }

    /**
     * Parts at or below their reorder level.
     *
     * Below the reorder level, not below zero. By the time stock is out, the
     * lead time has already been lost and the machine is already waiting.
     */
    public function lowStock(): View
    {
        $this->authorize('inventory.stock.view');

        $parts = SparePart::query()
            ->with('category')
            ->where('active', true)
            ->withSum('balances as on_hand', 'quantity_on_hand')
            ->get()
            ->filter(function (SparePart $part): bool {
                $onHand = number_format((float) ($part->on_hand ?? 0), 4, '.', '');

                return bccomp($onHand, (string) ($part->reorder_level ?? '0'), 4) <= 0;
            })
            // Critical spares first: a part whose absence stops a critical
            // machine is not the same problem as one that is merely low.
            ->sortByDesc(fn (SparePart $part) => [$part->is_critical_spare ? 1 : 0])
            ->values();

        return view('inventory::stock.low-stock', ['parts' => $parts]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('inventory.stock.receive');

        $validated = $request->validate([
            'spare_part_id' => ['required', 'string', 'size:26'],
            'bin_id' => ['required', 'string', 'size:26'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            // Required, not defaulted. Receiving at zero would drag the average
            // down and make every later issue look free.
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'transaction_type' => ['required', Rule::in(['RECEIPT', 'OPENING_BALANCE', 'ADJUSTMENT_IN'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->receive->handle(
            SparePart::findOrFail($validated['spare_part_id']),
            Bin::findOrFail($validated['bin_id']),
            (string) $validated['quantity'],
            (string) $validated['unit_cost'],
            $request->user()->id,
            $validated['notes'] ?? null,
            $validated['transaction_type'],
        );

        return back()->with('status', __('inventory.received'));
    }

    /**
     * Issuing and returning stock without a work order (SRS 23).
     *
     * The consumables case: a box of gloves to the dye house, a roll of tape to
     * the sewing floor. There is no machine to charge, so nothing is posted
     * against an asset — the ledger records that the stock left, and the
     * screen says plainly that it is not attributed to any repair.
     *
     * Anything fitted to a machine goes through its work order instead, which
     * is where the cost belongs and where the failure history can find it.
     */
    public function issueIndex(Request $request): View
    {
        $this->authorize('inventory.stock.view');

        return view('inventory::stock.issue', [
            'bins' => $this->accessibleBins(),
            'parts' => SparePart::where('active', true)
                ->orderBy('part_number')
                ->get(['id', 'part_number', 'name', 'unit']),
            'movements' => InventoryTransaction::query()
                ->whereIn('transaction_type', ['ISSUE', 'RETURN'])
                ->whereNull('work_order_id')
                ->with(['sparePart:id,part_number,name', 'bin'])
                ->orderByDesc('transaction_at')
                ->limit(50)
                ->get(),
        ]);
    }

    public function issue(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'spare_part_id' => ['required', 'string', 'size:26'],
            'bin_id' => ['required', 'string', 'size:26'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'transaction_type' => ['required', Rule::in(['ISSUE', 'RETURN'])],
            // Stock that moves with no work order behind it and no explanation
            // is indistinguishable from loss.
            'notes' => ['required', 'string', 'max:2000'],
        ]);

        $this->authorize($validated['transaction_type'] === 'ISSUE'
            ? 'inventory.stock.issue'
            : 'inventory.stock.return');

        $this->ledger->post(
            SparePart::findOrFail($validated['spare_part_id']),
            Bin::findOrFail($validated['bin_id']),
            $validated['transaction_type'],
            (string) $validated['quantity'],
            null,
            [
                'performed_by' => $request->user()->id,
                'notes' => $validated['notes'],
                'transaction_at' => CarbonImmutable::now(),
            ],
        );

        return back()->with('status', $validated['transaction_type'] === 'ISSUE'
            ? __('inventory.issued')
            : __('inventory.returned'));
    }

    public function adjust(Request $request): RedirectResponse
    {
        $this->authorize('inventory.adjustment.create');

        $validated = $request->validate([
            'spare_part_id' => ['required', 'string', 'size:26'],
            'bin_id' => ['required', 'string', 'size:26'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'transaction_type' => ['required', Rule::in(['ADJUSTMENT_OUT', 'SCRAP'])],
            // Stock that moves without an explanation is indistinguishable from
            // loss, so the reason is required rather than optional.
            'notes' => ['required', 'string', 'max:2000'],
        ]);

        $this->receive->adjustOut(
            SparePart::findOrFail($validated['spare_part_id']),
            Bin::findOrFail($validated['bin_id']),
            (string) $validated['quantity'],
            $validated['notes'],
            $request->user()->id,
            $validated['transaction_type'],
        );

        return back()->with('status', __('inventory.adjusted'));
    }

    public function reverse(Request $request, string $transaction): RedirectResponse
    {
        $this->authorize('inventory.adjustment.create');

        $original = InventoryTransaction::findOrFail($transaction);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $this->ledger->reverse($original, $request->user()->id, $validated['reason']);

        return back()->with('status', __('inventory.reversed'));
    }

    /**
     * @return array<string, string>
     */
    private function totals(): array
    {
        $balances = InventoryBalance::with('bin')->get();

        $value = '0.0000';
        $inTransit = '0.0000';

        foreach ($balances as $balance) {
            $value = bcadd($value, $balance->totalValue(), 4);

            if ($balance->bin?->is_in_transit) {
                $inTransit = bcadd($inTransit, (string) $balance->quantity_on_hand, 4);
            }
        }

        return [
            'value' => $value,
            'in_transit' => $inTransit,
            'lines' => (string) $balances->count(),
        ];
    }

    /**
     * @return Collection<int, Bin>
     */
    private function accessibleBins(): Collection
    {
        return Bin::where('active', true)
            ->with('store.warehouse')
            ->get()
            ->filter(fn (Bin $bin) => in_array(
                $bin->factoryId(), $this->context->accessibleFactoryIds(), true,
            ))
            ->values();
    }
}
