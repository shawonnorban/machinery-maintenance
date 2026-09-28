<?php

declare(strict_types=1);

namespace App\Modules\Vendor\Http\Controllers\Api;

use App\Modules\Vendor\Actions\ManageServiceContract;
use App\Modules\Vendor\Models\ServiceContract;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * AMC and service contracts, over the wire (API 18; mirrors the web
 * `CoverageController`'s contract half and `ServiceContractPolicy`, per
 * ADR-003).
 *
 * Contract value is commercial information, so this sits behind the vendor
 * view permission rather than the general asset one — unlike a warranty,
 * which a technician needs at the machine.
 */
class ServiceContractApiController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->allow('vendor.vendor.view_any');

        $query = ServiceContract::query()
            ->with(['vendor:id,name', 'asset:id,asset_code,name', 'factory:id,name'])
            ->when($request->boolean('expiring'), fn ($q) => $q->expiringWithin(60));

        $query = $this->applyFilters($query, $request, ['status', 'vendor_id']);
        $query = $this->applySort($query, $request, ['end_date', 'start_date'], 'end_date', 'asc');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (ServiceContract $contract): array => $this->summary($contract),
        );
    }

    public function store(Request $request, ManageServiceContract $action): JsonResponse
    {
        $this->allow('vendor.contract.manage');

        $contract = $action->create($this->validated($request), $this->caller()->auditUserId());

        return ApiResponse::created($this->detail($contract));
    }

    public function show(ServiceContract $contract): JsonResponse
    {
        $this->allow('vendor.vendor.view_any');

        $contract->load(['vendor:id,name', 'asset:id,asset_code,name', 'factory:id,name', 'assets', 'renewedFrom:id,contract_number']);

        return ApiResponse::ok($this->detail($contract));
    }

    public function renew(Request $request, ServiceContract $contract, ManageServiceContract $action): JsonResponse
    {
        $this->allow('vendor.contract.manage');

        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $renewal = $action->renew($contract, $data, $this->caller()->auditUserId());

        return ApiResponse::created($this->detail($renewal));
    }

    public function cancel(Request $request, ServiceContract $contract, ManageServiceContract $action): JsonResponse
    {
        $this->allow('vendor.contract.manage');

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $cancelled = $action->cancel($contract, $data['reason'], $this->caller()->auditUserId());

        return ApiResponse::ok($this->detail($cancelled));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
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

    /**
     * @return array<string, mixed>
     */
    private function summary(ServiceContract $contract): array
    {
        return [
            'id' => $contract->id,
            'contract_number' => $contract->contract_number,
            'contract_type' => $contract->contract_type,
            'status' => $contract->status,
            'vendor' => $contract->vendor === null ? null : ['id' => $contract->vendor->id, 'name' => $contract->vendor->name],
            'asset' => $contract->asset === null ? null : [
                'id' => $contract->asset->id, 'asset_code' => $contract->asset->asset_code, 'name' => $contract->asset->name,
            ],
            'factory' => $contract->factory === null ? null : ['id' => $contract->factory->id, 'name' => $contract->factory->name],
            'start_date' => $contract->start_date->toDateString(),
            'end_date' => $contract->end_date->toDateString(),
            'value' => $contract->value,
            'currency' => $contract->currency,
            'days_remaining' => $contract->daysRemaining(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(ServiceContract $contract): array
    {
        return $this->summary($contract) + [
            'renewal_date' => $contract->renewal_date?->toDateString(),
            'coverage' => $contract->coverage,
            'visits_per_year' => $contract->visits_per_year,
            'response_time_hours' => $contract->response_time_hours,
            'notes' => $contract->notes,
            'renewed_from_contract_id' => $contract->renewed_from_contract_id,
            'renewed_from_contract_number' => $contract->relationLoaded('renewedFrom') ? $contract->renewedFrom?->contract_number : null,
            // Same reasoning as Approval's `can_act` — the web decides this
            // with `@can('update', $contract)`.
            'can_manage' => $this->caller()->can('vendor.contract.manage')
                && ! in_array($contract->status, ['CANCELLED', 'RENEWED'], true),
            'assets' => $contract->relationLoaded('assets') ? $contract->assets->map(fn ($a): array => [
                'id' => $a->id, 'asset_code' => $a->asset_code, 'name' => $a->name,
            ])->all() : null,
        ];
    }
}
