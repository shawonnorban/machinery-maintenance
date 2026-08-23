<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers\Web;

use App\Modules\Asset\Models\AssetModel;
use App\Modules\Inventory\Actions\DeleteSparePart;
use App\Modules\Inventory\Actions\SaveSparePart;
use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\InventoryBalance;
use App\Modules\Inventory\Models\InventoryTransaction;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Models\SparePartCategory;
use App\Modules\Inventory\Models\SparePartCompatibility;
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

    public function store(Request $request, SaveSparePart $action): RedirectResponse
    {
        $this->authorize('inventory.part.create');

        $part = $action->create($this->validated($request, null));

        return redirect()
            ->route('app.inventory.parts.show', $part)
            ->with('status', __('inventory.part_created', ['number' => $part->part_number]));
    }

    public function edit(SparePart $part): View
    {
        $this->authorize('inventory.part.update');

        return view('inventory::parts.edit', [
            'part' => $part,
            'categories' => SparePartCategory::availableTo($this->context->companyId())
                ->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, SparePart $part, SaveSparePart $action): RedirectResponse
    {
        $this->authorize('inventory.part.update');

        $action->update($part, $this->validated($request, $part));

        return redirect()
            ->route('app.inventory.parts.show', $part)
            ->with('status', __('inventory.part_updated', ['number' => $part->part_number]));
    }

    /**
     * Retire a part from the catalogue, or bring it back.
     *
     * The way out for a part that was real: the ledger points at this row, and
     * a part nobody stocks any more is still the part that was fitted to a
     * machine two years ago. Deleting is for the row that was never real.
     */
    public function toggle(SparePart $part, SaveSparePart $action): RedirectResponse
    {
        $this->authorize('inventory.part.update');

        $action->setActive($part, ! $part->active);

        return back()->with('status', __('inventory.part_updated', ['number' => $part->part_number]));
    }

    /**
     * Remove a catalogue entry that should never have been created.
     *
     * Narrow on purpose: the action refuses anything the ledger or a work
     * order points at, because those are retired rather than removed. What is
     * left is the case this exists for — the part typed in twice, or under the
     * wrong number, which retiring would leave in every list for ever.
     */
    public function destroy(SparePart $part, DeleteSparePart $action): RedirectResponse
    {
        $this->authorize('inventory.part.update');

        $number = $part->part_number;

        $action->handle($part);

        return redirect()
            ->route('app.inventory.parts')
            ->with('status', __('inventory.part_deleted', ['number' => $number]));
    }

    /**
     * The rules, in one place, so creating and editing cannot drift apart.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?SparePart $part): array
    {
        $unique = Rule::unique('spare_parts')->where('company_id', $this->context->companyId());

        if ($part !== null) {
            $unique = $unique->ignore($part->id);
        }

        $validated = $request->validate([
            'part_number' => ['required', 'string', 'max:64', $unique],
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

        // Read from the request rather than the validated array: an unchecked
        // box is absent, which is not the same as false to array access.
        $validated['is_critical_spare'] = $request->boolean('is_critical_spare');
        $validated['hazardous'] = $request->boolean('hazardous');

        return $validated;
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
            // Which machines this part fits, and what will do instead when
            // the store is out of it (SRS 20).
            'compatibility' => SparePartCompatibility::where('spare_part_id', $part->id)
                ->with(['assetModel:id,model', 'substituteFor:id,part_number,name'])
                ->get(),
            'assetModels' => AssetModel::availableTo($this->context->companyId())
                ->where('active', true)->orderBy('model')->get(['id', 'model']),
            'otherParts' => SparePart::where('active', true)
                ->whereKeyNot($part->id)->orderBy('part_number')->get(['id', 'part_number', 'name']),
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
