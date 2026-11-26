<?php

declare(strict_types=1);

namespace App\Modules\Company\Http\Controllers\Api;

use App\Modules\Asset\Models\Asset;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Company Management → Machine Master and Machine Allocation (AnnotechERP
 * 01/04): machinery maintenance's own machine register, shown the Brickland
 * way. There is one machine record, not two — creating a machine, editing it
 * and moving it all stay with machinery maintenance (its form, its status
 * rules, its transfer history). This adds only the read model and capacity.
 */
class MachineRegisterApiController extends ApiController
{
    /**
     * Brickland's five machine states over the asset lifecycle (user-approved
     * mapping, AnnotechERP 01/04). Asset states with no Brickland name are
     * reported as they are.
     */
    public const STATUS_MAP = [
        'RUNNING' => 'ACTIVE',
        'IDLE' => 'IDLE',
        'UNDER_MAINTENANCE' => 'UNDER_MAINTENANCE',
        'BREAKDOWN' => 'DAMAGED',
        'RETIRED' => 'RETIRED',
    ];

    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): JsonResponse
    {
        $this->allow('asset.asset.view_any');

        $machines = Asset::query()
            ->whereIn('current_factory_id', $this->context->accessibleFactoryIds())
            ->with(['type', 'manufacturer', 'model', 'location.building', 'location.floor', 'location.department',
                'location.section', 'location.productionLine', 'location.workstation'])
            ->orderBy('asset_code')
            ->limit(1000)
            ->get();

        return ApiResponse::ok($machines->map(fn (Asset $asset): array => $this->summary($asset))->all());
    }

    /** Capacity is the one machine field Company Management owns. */
    public function update(Request $request, Asset $asset): JsonResponse
    {
        $this->allow('asset.asset.update');
        abort_unless(in_array($asset->current_factory_id, $this->context->accessibleFactoryIds(), true), 404);

        $data = $request->validate([
            'capacity' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'capacity_uom' => ['nullable', 'string', 'max:16'],
        ]);

        $asset->forceFill($data)->save();

        return ApiResponse::ok($this->summary($asset->fresh(['type', 'manufacturer', 'model', 'location'])));
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Asset $asset): array
    {
        $location = $asset->location;

        return [
            'id' => $asset->id,
            'version' => $asset->version,
            'code' => $asset->asset_code,
            'name' => $asset->name,
            'type' => $asset->type?->name,
            'brand' => $asset->manufacturer?->name,
            'model' => $asset->model?->model,
            'serial_number' => $asset->serial_number,
            'purchase_date' => $asset->purchase_date?->format('Y-m-d'),
            'installation_date' => $asset->installation_date?->format('Y-m-d'),
            'capacity' => $asset->capacity,
            'capacity_uom' => $asset->capacity_uom,
            'asset_status' => $asset->status,
            'status' => self::STATUS_MAP[$asset->status] ?? $asset->status,
            'location' => $location?->name,
            'building' => $location?->building?->name,
            'floor' => $location?->floor?->name,
            'department' => $location?->department?->name,
            'section' => $location?->section?->name,
            'line' => $location?->productionLine?->name,
            'workstation' => $location?->workstation?->name,
        ];
    }
}
