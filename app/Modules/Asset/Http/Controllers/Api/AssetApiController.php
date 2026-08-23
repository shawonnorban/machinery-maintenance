<?php

declare(strict_types=1);

namespace App\Modules\Asset\Http\Controllers\Api;

use App\Modules\Asset\Models\Asset;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Machines, over the wire (API 6).
 *
 * Read-only on purpose, for now. Every integration starts here — an ERP
 * reconciling its fixed asset register, a dashboard listing what is down — and
 * none of those need to create machines. Creating one is a decision with a
 * commissioning workflow behind it, and an endpoint that skipped that would be
 * a way to put an uncommissioned machine into production by accident.
 */
class AssetApiController extends ApiController
{
    /** Columns a client may filter on. Nothing else reaches SQL (API 30). */
    private const FILTERS = [
        'status', 'criticality', 'current_factory_id', 'asset_location_id',
        'asset_type_id', 'asset_category_id', 'manufacturer_id',
    ];

    private const SORTS = ['asset_code', 'name', 'criticality', 'status', 'created_at'];

    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): JsonResponse
    {
        $this->allow('asset.asset.view_any');

        $query = Asset::query()
            ->with(['type:id,name', 'category:id,name', 'factory:id,name', 'location:id,name'])
            // The tenant scope already narrows to the company; this narrows to
            // the factories this particular caller reaches.
            ->whereIn('current_factory_id', $this->context->accessibleFactoryIds());

        if (is_string($search = $request->query('search')) && $search !== '') {
            $term = '%'.$search.'%';
            $query->where(fn ($q) => $q->where('asset_code', 'like', $term)
                ->orWhere('name', 'like', $term)
                ->orWhere('serial_number', 'like', $term));
        }

        $query = $this->applyFilters($query, $request, self::FILTERS);
        $query = $this->applySort($query, $request, self::SORTS, 'asset_code', 'asc');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (Asset $asset): array => $this->summary($asset),
        );
    }

    public function show(Asset $asset): JsonResponse
    {
        $this->allow('asset.asset.view');
        $this->assertReachable($asset);

        $asset->load([
            'type:id,name', 'category:id,name', 'manufacturer:id,name',
            'model:id,model', 'factory:id,name', 'location:id,name', 'parent:id,asset_code,name',
        ]);

        return ApiResponse::ok($this->detail($asset));
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Asset $asset): array
    {
        return [
            'id' => $asset->id,
            'asset_code' => $asset->asset_code,
            'name' => $asset->name,
            'status' => $asset->status,
            'criticality' => $asset->criticality,
            'serial_number' => $asset->serial_number,
            'type' => $asset->type?->name,
            'category' => $asset->category?->name,
            'factory' => ['id' => $asset->current_factory_id, 'name' => $asset->factory?->name],
            'location' => ['id' => $asset->asset_location_id, 'name' => $asset->location?->name],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Asset $asset): array
    {
        return $this->summary($asset) + [
            'description' => $asset->description,
            'manufacturer' => $asset->manufacturer?->name,
            'model' => $asset->model?->model,
            'parent' => $asset->parent === null ? null : [
                'id' => $asset->parent->id,
                'asset_code' => $asset->parent->asset_code,
                'name' => $asset->parent->name,
            ],
            'purchase_date' => $asset->purchase_date?->toDateString(),
            'installation_date' => $asset->installation_date?->toDateString(),
            'commissioning_date' => $asset->commissioning_date?->toDateString(),
            'warranty' => [
                'start' => $asset->warranty_start?->toDateString(),
                'end' => $asset->warranty_end?->toDateString(),
                // Answered rather than left to the client to work out. Two
                // clients computing "is it still under warranty" from dates
                // will disagree about the last day.
                'active' => $asset->warrantyIsActive(),
            ],
            'created_at' => $asset->created_at?->toIso8601String(),
            'updated_at' => $asset->updated_at?->toIso8601String(),
        ];
    }

    /**
     * A machine in a factory this caller cannot reach is a machine that does
     * not exist, as far as the answer goes (API 2).
     */
    private function assertReachable(Asset $asset): void
    {
        if (! $this->context->canAccessFactory((string) $asset->current_factory_id)) {
            abort(404);
        }
    }
}
