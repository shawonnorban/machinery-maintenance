<?php

declare(strict_types=1);

namespace App\Modules\Vendor\Http\Controllers\Api;

use App\Modules\Vendor\Models\Vendor;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Suppliers and service providers, over the wire (API 17; mirrors the web
 * `VendorController` and `VendorPolicy`, per ADR-003).
 *
 * Vendors are company-wide, not factory-scoped: a supplier serves whichever
 * factories buy from them.
 */
class VendorApiController extends ApiController
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): JsonResponse
    {
        $this->allow('vendor.vendor.view_any');

        $query = Vendor::query()
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.$request->string('q').'%')
                ->orWhere('code', 'like', '%'.$request->string('q').'%')))
            ->withCount(['warranties', 'contracts'])
            ->orderBy('name');

        $query = $this->applyFilters($query, $request, ['vendor_type', 'status']);

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (Vendor $vendor): array => $this->summary($vendor),
        );
    }

    public function show(Vendor $vendor): JsonResponse
    {
        $this->allow('vendor.vendor.view_any');

        return ApiResponse::ok($this->summary($vendor));
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('vendor.vendor.create');

        $data = $this->validated($request, null);

        $vendor = Vendor::create([...$data, 'created_by' => $this->caller()->auditUserId()]);

        return ApiResponse::created($this->summary($vendor));
    }

    public function update(Request $request, Vendor $vendor): JsonResponse
    {
        $this->allow('vendor.vendor.update');

        $vendor->update($this->validated($request, $vendor));

        return ApiResponse::ok($this->summary($vendor->fresh()));
    }

    /**
     * Archive, never delete. A vendor named on a five-year-old cost entry has
     * to stay resolvable (ADR-057).
     */
    public function destroy(Vendor $vendor): JsonResponse
    {
        $this->allow('vendor.vendor.update');

        $vendor->update(['status' => 'INACTIVE']);
        $vendor->delete();

        return ApiResponse::noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Vendor $vendor): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required', 'string', 'max:48',
                Rule::unique('vendors', 'code')
                    ->where('company_id', $vendor?->company_id ?? $this->context->companyId())
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

    /**
     * @return array<string, mixed>
     */
    private function summary(Vendor $vendor): array
    {
        return [
            'id' => $vendor->id,
            'name' => $vendor->name,
            'code' => $vendor->code,
            'vendor_type' => $vendor->vendor_type,
            'contact_name' => $vendor->contact_name,
            'phone' => $vendor->phone,
            'email' => $vendor->email,
            'address' => $vendor->address,
            'tax_reference' => $vendor->tax_reference,
            'status' => $vendor->status,
            'notes' => $vendor->notes,
            'warranties_count' => $vendor->warranties_count ?? null,
            'contracts_count' => $vendor->contracts_count ?? null,
            'created_at' => $vendor->created_at?->toIso8601String(),
        ];
    }
}
