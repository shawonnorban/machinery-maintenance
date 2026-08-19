<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers\Web;

use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\InventoryBalance;
use App\Modules\Inventory\Models\InventoryTransaction;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Models\SparePartCategory;
use App\Modules\Inventory\Services\InventoryLedger;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SparePartController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly InventoryLedger $ledger,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('inventory.part.view_any');

        $parts = SparePart::query()
            ->with('category')
            ->withSum('balances as on_hand', 'quantity_on_hand')
            ->withSum('balances as reserved', 'quantity_reserved')
            ->when(filled($request->query('search')), function ($q) use ($request): void {
                $term = '%'.$request->query('search').'%';
                $q->where(fn ($w) => $w->where('part_number', 'like', $term)
                    ->orWhere('name', 'like', $term)
                    ->orWhere('brand', 'like', $term));
            })
            ->when(filled($request->query('category_id')), fn ($q) => $q->where('category_id', $request->query('category_id')))
            ->when($request->query('critical') === '1', fn ($q) => $q->where('is_critical_spare', true))
            ->orderBy('part_number')
            ->paginate(min(max((int) $request->query('per_page', 25), 10), 100))
            ->withQueryString();

        return view('inventory::parts.index', [
            'parts' => $parts,
            'categories' => SparePartCategory::availableTo($this->context->companyId())
                ->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('inventory.part.create');

        return view('inventory::parts.create', [
            'categories' => SparePartCategory::availableTo($this->context->companyId())
                ->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('inventory.part.create');

        $validated = $request->validate([
            'part_number' => [
                'required', 'string', 'max:64',
                Rule::unique('spare_parts')->where('company_id', $this->context->companyId()),
            ],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'string', 'size:26'],
            'brand' => ['nullable', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'unit' => ['required', Rule::in(SparePart::UNITS)],
            // Thresholds, not quantities. Stock comes from the ledger.
            'minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'shelf_life_days' => ['nullable', 'integer', 'min:0', 'max:36500'],
            'is_critical_spare' => ['sometimes', 'boolean'],
            'hazardous' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $part = SparePart::create([
            'part_number' => $validated['part_number'],
            'name' => $validated['name'],
            'category_id' => filled($validated['category_id'] ?? null) ? $validated['category_id'] : null,
            'brand' => $validated['brand'] ?? null,
            'manufacturer' => $validated['manufacturer'] ?? null,
            'unit' => $validated['unit'],
            'minimum_stock' => $validated['minimum_stock'] ?? '0',
            'reorder_level' => $validated['reorder_level'] ?? '0',
            'lead_time_days' => $validated['lead_time_days'] ?? null,
            'shelf_life_days' => $validated['shelf_life_days'] ?? null,
            'is_critical_spare' => $request->boolean('is_critical_spare'),
            'hazardous' => $request->boolean('hazardous'),
            'notes' => $validated['notes'] ?? null,
            'currency' => 'BDT',
            'active' => true,
        ]);

        return redirect()
            ->route('app.inventory.parts.show', $part)
            ->with('status', __('inventory.received'));
    }

    public function show(SparePart $part): View
    {
        $this->authorize('inventory.part.view_any');

        $balances = InventoryBalance::where('spare_part_id', $part->id)
            ->with('bin.store.warehouse')
            ->get();

        $canSeeStock = request()->user()->can('inventory.stock.view');

        return view('inventory::parts.show', [
            'part' => $part,
            'balances' => $balances,
            'canSeeStock' => $canSeeStock,
            // The ledger, most recent first. Capped because a fast-moving part
            // accumulates thousands of rows and nobody reads page 400.
            'transactions' => $canSeeStock
                ? InventoryTransaction::where('spare_part_id', $part->id)
                    ->with(['bin', 'workOrder:id,work_order_number'])
                    ->orderByDesc('transaction_at')
                    ->orderByDesc('id')
                    ->limit(100)
                    ->get()
                : collect(),
            // Proof rather than assertion: the screen states whether the ledger
            // still replays to the balance it shows.
            'verification' => $canSeeStock
                ? $balances->map(fn (InventoryBalance $b) => [
                    'bin' => $b->bin,
                    'result' => $this->ledger->verify($part, $b->bin),
                ])
                : collect(),
            'bins' => Bin::where('is_in_transit', false)
                ->where('active', true)
                ->with('store.warehouse')
                ->get()
                ->filter(fn (Bin $bin) => in_array(
                    $bin->factoryId(), $this->context->accessibleFactoryIds(), true,
                ))
                ->values(),
        ]);
    }
}
