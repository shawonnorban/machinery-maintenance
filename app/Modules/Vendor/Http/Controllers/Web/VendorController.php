<?php

declare(strict_types=1);

namespace App\Modules\Vendor\Http\Controllers\Web;

use App\Modules\Vendor\Models\ServiceContract;
use App\Modules\Vendor\Models\Vendor;
use App\Modules\Vendor\Models\Warranty;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Suppliers and service providers (SRS 26).
 */
class VendorController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Vendor::class);

        $vendors = Vendor::query()
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.$request->string('q').'%')
                ->orWhere('code', 'like', '%'.$request->string('q').'%')))
            ->when($request->filled('type'), fn ($q) => $q->where('vendor_type', $request->string('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->withCount(['warranties', 'contracts'])
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('vendor::vendors.index', [
            'vendors' => $vendors,
            'types' => Vendor::TYPES,
            'statuses' => Vendor::STATUSES,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Vendor::class);

        return view('vendor::vendors.create', [
            'types' => Vendor::TYPES,
            'statuses' => Vendor::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Vendor::class);

        $data = $this->validated($request);

        $vendor = Vendor::create([...$data, 'created_by' => $request->user()->id]);

        return redirect()
            ->route('app.vendors.show', $vendor)
            ->with('status', __('vendor.created'));
    }

    public function show(Vendor $vendor): View
    {
        $this->authorize('view', $vendor);

        return view('vendor::vendors.show', [
            'vendor' => $vendor,
            'warranties' => Warranty::with('asset')
                ->where('vendor_id', $vendor->id)
                ->orderByDesc('end_date')
                ->limit(20)
                ->get(),
            'contracts' => ServiceContract::with(['asset', 'factory'])
                ->where('vendor_id', $vendor->id)
                ->orderByDesc('end_date')
                ->limit(20)
                ->get(),
        ]);
    }

    public function edit(Vendor $vendor): View
    {
        $this->authorize('update', $vendor);

        return view('vendor::vendors.edit', [
            'vendor' => $vendor,
            'types' => Vendor::TYPES,
            'statuses' => Vendor::STATUSES,
        ]);
    }

    public function update(Request $request, Vendor $vendor): RedirectResponse
    {
        $this->authorize('update', $vendor);

        $vendor->update($this->validated($request, $vendor));

        return redirect()
            ->route('app.vendors.show', $vendor)
            ->with('status', __('vendor.updated'));
    }

    /**
     * Archive, never delete. A vendor named on a five-year-old cost entry has
     * to stay resolvable (ADR-057).
     */
    public function destroy(Request $request, Vendor $vendor): RedirectResponse
    {
        $this->authorize('update', $vendor);

        $vendor->update(['status' => 'INACTIVE']);
        $vendor->delete();

        return redirect()->route('app.vendors.index')->with('status', __('vendor.archived'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Vendor $vendor = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required', 'string', 'max:48',
                Rule::unique('vendors', 'code')
                    ->where('company_id', $vendor?->company_id ?? app(TenantContext::class)->companyId())
                    ->whereNull('deleted_at')
                    ->ignore($vendor?->id),
            ],
            'vendor_type' => ['required', Rule::in(Vendor::TYPES)],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'tax_reference' => ['nullable', 'string', 'max:64'],
            'status' => ['required', Rule::in(Vendor::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
