<?php

declare(strict_types=1);

namespace App\Modules\Vendor\Http\Controllers\Api;

use App\Modules\Finance\Models\PaymentType;
use App\Modules\TradeExport\Models\TxBank;
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

        $query = $this->applyFilters($query, $request, ['vendor_type', 'status', 'approval_status']);

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (Vendor $vendor): array => $this->summary($vendor),
        );
    }

    /**
     * The bank and payment-type pickers the create/edit form needs. Gated
     * on the vendor permission rather than `masterdata.manage`, for the
     * same reason `GET /spare-parts/form-options` is: the person who
     * maintains suppliers is not necessarily the one who maintains master
     * data.
     */
    public function formOptions(): JsonResponse
    {
        $this->allow('vendor.vendor.view_any');

        $companyId = $this->context->companyId();

        return ApiResponse::ok([
            // The one bank list (Finance → Manage Bank, Trade Export → Bank List).
            'banks' => TxBank::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->all(),
            'payment_types' => PaymentType::availableTo($companyId)->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->all(),
            'supply_categories' => Vendor::SUPPLY_CATEGORIES,
            'approval_statuses' => Vendor::APPROVAL_STATUSES,
        ]);
    }

    public function show(Vendor $vendor): JsonResponse
    {
        $this->allow('vendor.vendor.view_any');

        return ApiResponse::ok($this->summary(
            $vendor->load('bank:id,name', 'defaultPaymentType:id,name')->loadCount(['warranties', 'contracts']),
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('vendor.vendor.create');

        $data = $this->validated($request, null);

        $vendor = Vendor::create([...$data, 'created_by' => $this->caller()->auditUserId()]);

        return ApiResponse::created($this->summary($vendor->load('bank:id,name', 'defaultPaymentType:id,name')));
    }

    public function update(Request $request, Vendor $vendor): JsonResponse
    {
        $this->allow('vendor.vendor.update');

        $vendor->update($this->validated($request, $vendor));

        return ApiResponse::ok($this->summary($vendor->fresh()->load('bank:id,name', 'defaultPaymentType:id,name')));
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
        $companyId = $this->context->companyId();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required', 'string', 'max:48',
                Rule::unique('vendors', 'code')
                    ->where('company_id', $vendor?->company_id ?? $companyId)
                    ->whereNull('deleted_at')
                    ->ignore($vendor?->id),
            ],
            'vendor_type' => ['required', Rule::in(Vendor::TYPES)],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'country_of_origin' => ['nullable', 'string', 'max:100'],
            'tax_reference' => ['nullable', 'string', 'max:64'],
            'bin' => ['nullable', 'string', 'max:32'],
            'trade_license_no' => ['nullable', 'string', 'max:64'],
            'bank_id' => ['nullable', 'string', 'size:26', Rule::exists('tx_banks', 'id')->where('company_id', $companyId)],
            'bank_branch' => ['nullable', 'string', 'max:255'],
            'bank_account_no' => ['nullable', 'string', 'max:64'],
            'bank_swift' => ['nullable', 'string', 'max:16'],
            'supply_categories' => ['sometimes', 'array'],
            'supply_categories.*' => [Rule::in(Vendor::SUPPLY_CATEGORIES)],
            'status' => ['required', Rule::in(Vendor::STATUSES)],
            'approval_status' => ['sometimes', Rule::in(Vendor::APPROVAL_STATUSES)],
            'default_currency' => ['nullable', 'string', 'size:3'],
            'default_payment_type_id' => ['nullable', 'string', 'size:26', Rule::exists('payment_types', 'id')->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $companyId))],
            'default_payment_terms' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (array_key_exists('supply_categories', $validated)) {
            $validated['supply_categories_json'] = array_values(array_unique($validated['supply_categories']));
            unset($validated['supply_categories']);
        }

        if (isset($validated['default_currency'])) {
            $validated['default_currency'] = strtoupper($validated['default_currency']);
        }

        return $validated;
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
            'country_of_origin' => $vendor->country_of_origin,
            'tax_reference' => $vendor->tax_reference,
            'bin' => $vendor->bin,
            'trade_license_no' => $vendor->trade_license_no,
            'bank' => $vendor->relationLoaded('bank') && $vendor->bank !== null
                ? ['id' => $vendor->bank_id, 'name' => $vendor->bank->name]
                : ($vendor->bank_id === null ? null : ['id' => $vendor->bank_id]),
            'bank_branch' => $vendor->bank_branch,
            'bank_account_no' => $vendor->bank_account_no,
            'bank_swift' => $vendor->bank_swift,
            'supply_categories' => $vendor->supply_categories_json ?? [],
            'status' => $vendor->status,
            'approval_status' => $vendor->approval_status,
            'default_currency' => $vendor->default_currency,
            'default_payment_type' => $vendor->relationLoaded('defaultPaymentType') && $vendor->defaultPaymentType !== null
                ? ['id' => $vendor->default_payment_type_id, 'name' => $vendor->defaultPaymentType->name]
                : ($vendor->default_payment_type_id === null ? null : ['id' => $vendor->default_payment_type_id]),
            'default_payment_terms' => $vendor->default_payment_terms,
            'rating' => $vendor->rating,
            'notes' => $vendor->notes,
            'warranties_count' => $vendor->warranties_count ?? null,
            'contracts_count' => $vendor->contracts_count ?? null,
            'created_at' => $vendor->created_at?->toIso8601String(),
        ];
    }
}
