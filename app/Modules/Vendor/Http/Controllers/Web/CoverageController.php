<?php

declare(strict_types=1);

namespace App\Modules\Vendor\Http\Controllers\Web;

use App\Modules\Asset\Models\Asset;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Vendor\Actions\DecideWarrantyClaim;
use App\Modules\Vendor\Actions\FileWarrantyClaim;
use App\Modules\Vendor\Actions\ManageServiceContract;
use App\Modules\Vendor\Actions\RecordWarranty;
use App\Modules\Vendor\Models\ServiceContract;
use App\Modules\Vendor\Models\Vendor;
use App\Modules\Vendor\Models\Warranty;
use App\Modules\Vendor\Models\WarrantyClaim;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Warranties, claims and AMC contracts (SRS 26).
 *
 * Both lists lead with what is about to run out rather than with everything
 * ever recorded. A page of expired cover is a filing cabinet; the useful screen
 * is the one that says what needs a decision this month.
 */
class CoverageController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function warranties(Request $request): View
    {
        $this->authorize('viewAny', Warranty::class);

        $warranties = Warranty::query()
            ->with(['asset', 'vendor'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->boolean('expiring'), fn ($q) => $q->expiringWithin(60))
            ->orderBy('end_date')
            ->paginate(25)
            ->withQueryString();

        return view('vendor::warranties.index', [
            'warranties' => $warranties,
            'statuses' => Warranty::STATUSES,
            'expiringCount' => Warranty::query()->expiringWithin(60)->count(),
            'openClaims' => WarrantyClaim::whereIn('status', WarrantyClaim::OPEN_STATUSES)->count(),
        ]);
    }

    public function createWarranty(Request $request): View
    {
        $this->authorize('create', Warranty::class);

        return view('vendor::warranties.create', [
            'assets' => $this->assets(),
            'vendors' => $this->vendors(),
            'types' => Warranty::TYPES,
            'assetId' => $request->query('asset_id'),
        ]);
    }

    public function storeWarranty(Request $request, RecordWarranty $action): RedirectResponse
    {
        $this->authorize('create', Warranty::class);

        $data = $request->validate([
            'asset_id' => ['required', 'string'],
            'vendor_id' => ['nullable', 'string'],
            'warranty_type' => ['required', Rule::in(Warranty::TYPES)],
            'reference' => ['nullable', 'string', 'max:64'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'coverage' => ['nullable', 'string', 'max:2000'],
            'exclusions' => ['nullable', 'string', 'max:2000'],
        ]);

        $warranty = $action->handle($data, $request->user()->id);

        return redirect()
            ->route('app.warranties.show', $warranty)
            ->with('status', __('vendor.warranty_recorded'));
    }

    public function showWarranty(Warranty $warranty): View
    {
        $this->authorize('view', $warranty);

        return view('vendor::warranties.show', [
            'warranty' => $warranty->load(['asset', 'vendor']),
            'claims' => $warranty->claims()->with('breakdown')->orderByDesc('claim_date')->get(),
        ]);
    }

    public function storeClaim(Request $request, Warranty $warranty, FileWarrantyClaim $action): RedirectResponse
    {
        $this->authorize('update', $warranty);

        $data = $request->validate([
            'claim_date' => ['required', 'date'],
            'incident_date' => ['nullable', 'date'],
            'description' => ['required', 'string', 'max:2000'],
            'claimed_amount' => ['nullable', 'numeric', 'min:0'],
            'breakdown_id' => ['nullable', 'string'],
            'work_order_id' => ['nullable', 'string'],
        ]);

        $action->handle($warranty, $data, $request->user()->id);

        return redirect()
            ->route('app.warranties.show', $warranty)
            ->with('status', __('vendor.claim_filed'));
    }

    public function decideClaim(Request $request, WarrantyClaim $claim, DecideWarrantyClaim $action): RedirectResponse
    {
        $this->authorize('update', $claim);

        $data = $request->validate([
            'status' => ['required', Rule::in(WarrantyClaim::STATUSES)],
            'resolution' => ['nullable', 'string', 'max:2000'],
            'settled_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $action->handle($claim, $data['status'], $data, $request->user()->id);

        return back()->with('status', __('vendor.claim_updated'));
    }

    public function contracts(Request $request): View
    {
        $this->authorize('viewAny', ServiceContract::class);

        $contracts = ServiceContract::query()
            ->with(['vendor', 'asset', 'factory'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->boolean('expiring'), fn ($q) => $q->expiringWithin(60))
            ->orderBy('end_date')
            ->paginate(25)
            ->withQueryString();

        return view('vendor::contracts.index', [
            'contracts' => $contracts,
            'statuses' => ServiceContract::STATUSES,
            'expiringCount' => ServiceContract::query()->expiringWithin(60)->count(),
        ]);
    }

    public function createContract(): View
    {
        $this->authorize('create', ServiceContract::class);

        return view('vendor::contracts.create', [
            'vendors' => $this->vendors(services: true),
            'assets' => $this->assets(),
            'factories' => Factory::whereIn('id', $this->context->accessibleFactoryIds())->orderBy('name')->get(),
            'types' => ServiceContract::TYPES,
        ]);
    }

    public function storeContract(Request $request, ManageServiceContract $action): RedirectResponse
    {
        $this->authorize('create', ServiceContract::class);

        $data = $this->contractRules($request);

        $contract = $action->create($data, $request->user()->id);

        return redirect()
            ->route('app.service-contracts.show', $contract)
            ->with('status', __('vendor.contract_created'));
    }

    public function showContract(ServiceContract $contract): View
    {
        $this->authorize('view', $contract);

        return view('vendor::contracts.show', [
            'contract' => $contract->load(['vendor', 'asset', 'factory', 'assets', 'renewedFrom']),
        ]);
    }

    public function renewContract(Request $request, ServiceContract $contract, ManageServiceContract $action): RedirectResponse
    {
        $this->authorize('update', $contract);

        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $renewal = $action->renew($contract, $data, $request->user()->id);

        return redirect()
            ->route('app.service-contracts.show', $renewal)
            ->with('status', __('vendor.contract_renewed'));
    }

    public function cancelContract(Request $request, ServiceContract $contract, ManageServiceContract $action): RedirectResponse
    {
        $this->authorize('update', $contract);

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $action->cancel($contract, $data['reason'], $request->user()->id);

        return back()->with('status', __('vendor.contract_cancelled'));
    }

    /**
     * @return array<string, mixed>
     */
    private function contractRules(Request $request): array
    {
        return $request->validate([
            'vendor_id' => ['required', 'string'],
            'asset_id' => ['nullable', 'string'],
            'factory_id' => ['nullable', 'string'],
            'asset_ids' => ['nullable', 'array'],
            'asset_ids.*' => ['string'],
            'contract_number' => ['nullable', 'string', 'max:32'],
            'contract_type' => ['required', Rule::in(ServiceContract::TYPES)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'renewal_date' => ['nullable', 'date'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'coverage' => ['nullable', 'string', 'max:2000'],
            'visits_per_year' => ['nullable', 'integer', 'min:0', 'max:365'],
            'response_time_hours' => ['nullable', 'integer', 'min:0', 'max:8760'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function vendors(bool $services = false)
    {
        return Vendor::where('status', 'ACTIVE')
            ->when($services, fn ($q) => $q->whereIn('vendor_type', ['SERVICE', 'BOTH']))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    private function assets()
    {
        return Asset::query()
            ->whereNotIn('status', ['SCRAPPED', 'LOST'])
            ->orderBy('asset_code')
            ->limit(500)
            ->get(['id', 'asset_code', 'name']);
    }
}
