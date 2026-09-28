<?php

declare(strict_types=1);

namespace App\Modules\Vendor\Http\Controllers\Api;

use App\Modules\Vendor\Actions\DecideWarrantyClaim;
use App\Modules\Vendor\Actions\FileWarrantyClaim;
use App\Modules\Vendor\Actions\RecordWarranty;
use App\Modules\Vendor\Models\Warranty;
use App\Modules\Vendor\Models\WarrantyClaim;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Cover on a machine, over the wire (API 18; mirrors the web
 * `CoverageController`'s warranty half and `WarrantyPolicy`, per ADR-003).
 *
 * Reading is deliberately wider than managing: a technician standing at a
 * broken machine has to be able to see the repair is already paid for.
 */
class WarrantyApiController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->allow('asset.asset.view_any');

        $query = Warranty::query()
            ->with(['asset:id,asset_code,name', 'vendor:id,name'])
            ->when($request->boolean('expiring'), fn ($q) => $q->expiringWithin(60));

        $query = $this->applyFilters($query, $request, ['status', 'asset_id']);
        $query = $this->applySort($query, $request, ['end_date', 'start_date'], 'end_date', 'asc');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (Warranty $warranty): array => $this->summary($warranty),
        );
    }

    public function store(Request $request, RecordWarranty $action): JsonResponse
    {
        $this->allow('vendor.warranty.manage');

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

        $warranty = $action->handle($data, $this->caller()->auditUserId());

        return ApiResponse::created($this->detail($warranty));
    }

    public function show(Warranty $warranty): JsonResponse
    {
        $this->allow('asset.asset.view_any');

        $warranty->load(['asset:id,asset_code,name', 'vendor:id,name', 'claims']);

        return ApiResponse::ok($this->detail($warranty));
    }

    public function storeClaim(Request $request, Warranty $warranty, FileWarrantyClaim $action): JsonResponse
    {
        $this->allow('vendor.warranty.manage');

        $data = $request->validate([
            'claim_date' => ['required', 'date'],
            'incident_date' => ['nullable', 'date'],
            'description' => ['required', 'string', 'max:2000'],
            'claimed_amount' => ['nullable', 'numeric', 'min:0'],
            'breakdown_id' => ['nullable', 'string'],
            'work_order_id' => ['nullable', 'string'],
        ]);

        $claim = $action->handle($warranty, $data, $this->caller()->auditUserId());

        return ApiResponse::created($this->claimSummary($claim));
    }

    public function decideClaim(Request $request, WarrantyClaim $claim, DecideWarrantyClaim $action): JsonResponse
    {
        $this->allow('vendor.warranty.manage');

        $data = $request->validate([
            'status' => ['required', Rule::in(WarrantyClaim::STATUSES)],
            'resolution' => ['nullable', 'string', 'max:2000'],
            'settled_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $updated = $action->handle($claim, $data['status'], $data, $this->caller()->auditUserId());

        return ApiResponse::ok($this->claimSummary($updated));
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Warranty $warranty): array
    {
        return [
            'id' => $warranty->id,
            'asset' => $warranty->asset === null ? null : [
                'id' => $warranty->asset->id,
                'asset_code' => $warranty->asset->asset_code,
                'name' => $warranty->asset->name,
            ],
            'vendor' => $warranty->vendor === null ? null : [
                'id' => $warranty->vendor->id,
                'name' => $warranty->vendor->name,
            ],
            'warranty_type' => $warranty->warranty_type,
            'status' => $warranty->status,
            'start_date' => $warranty->start_date->toDateString(),
            'end_date' => $warranty->end_date->toDateString(),
            'days_remaining' => $warranty->daysRemaining(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Warranty $warranty): array
    {
        return $this->summary($warranty) + [
            'reference' => $warranty->reference,
            'coverage' => $warranty->coverage,
            'exclusions' => $warranty->exclusions,
            // The web decides this with `@can('update', $warranty)`; a
            // client has no such local check, so it travels here instead —
            // same reasoning as Approval's `can_act`.
            'can_manage' => $this->caller()->can('vendor.warranty.manage'),
            'claims' => $warranty->relationLoaded('claims') ? $warranty->claims->map(
                fn (WarrantyClaim $c): array => $this->claimSummary($c),
            )->all() : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function claimSummary(WarrantyClaim $claim): array
    {
        return [
            'id' => $claim->id,
            'warranty_id' => $claim->warranty_id,
            'claim_number' => $claim->claim_number,
            'claim_date' => $claim->claim_date->toDateString(),
            'description' => $claim->description,
            'status' => $claim->status,
            'claimed_amount' => $claim->claimed_amount,
            'settled_amount' => $claim->settled_amount,
            'currency' => $claim->currency,
            'resolution' => $claim->resolution,
            'resolved_at' => $claim->resolved_at?->toDateString(),
        ];
    }
}
