<?php

declare(strict_types=1);

namespace App\Modules\Asset\Http\Controllers\Api;

use App\Modules\Asset\Actions\DeleteAssetLocation;
use App\Modules\Asset\Actions\SaveAssetLocation;
use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Where machines live, over the wire (API 5, ADR-052; mirrors the web
 * `AssetLocationController`, which this delegates every write to unchanged
 * per ADR-003).
 */
class AssetLocationApiController extends ApiController
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): JsonResponse
    {
        $this->allow('masterdata.manage');

        $query = AssetLocation::query()
            ->with(['factory:id,name,code', 'productionLine:id,name'])
            ->withCount('assets')
            ->when($request->query('factory_id'), fn ($q, $id) => $q->where('factory_id', $id))
            ->when($request->string('search')->trim()->toString(), function ($q, string $term): void {
                $q->where(fn ($w) => $w->where('code', 'like', $term.'%')->orWhere('name', 'like', $term.'%'));
            })
            ->orderBy('code');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (AssetLocation $location): array => $this->summary($location),
        );
    }

    public function forFactory(Request $request, Factory $factory): JsonResponse
    {
        $this->allow('masterdata.manage');

        $locations = AssetLocation::query()
            ->where('factory_id', $factory->id)
            ->withCount('assets')
            ->orderBy('code')
            ->get();

        return ApiResponse::ok($locations->map(fn (AssetLocation $l): array => $this->summary($l))->all());
    }

    public function show(AssetLocation $location): JsonResponse
    {
        $this->allow('masterdata.manage');

        return ApiResponse::ok($this->summary($location));
    }

    public function store(Request $request, SaveAssetLocation $action): JsonResponse
    {
        $this->allow('masterdata.manage');

        return ApiResponse::created($this->summary($action->create($this->validated($request, null))));
    }

    public function update(Request $request, AssetLocation $location, SaveAssetLocation $action): JsonResponse
    {
        $this->allow('masterdata.manage');

        return ApiResponse::ok($this->summary($action->update($location, $this->validated($request, $location))));
    }

    /**
     * A closed location keeps every machine that ever stood in it readable;
     * it simply stops being offered when somebody registers or moves one.
     */
    public function setActive(Request $request, AssetLocation $location, SaveAssetLocation $action): JsonResponse
    {
        $this->allow('masterdata.manage');

        $data = $request->validate(['active' => ['required', 'boolean']]);

        return ApiResponse::ok(
            $this->summary($action->setStatus($location, $data['active'] ? 'ACTIVE' : 'INACTIVE')),
        );
    }

    public function destroy(AssetLocation $location, DeleteAssetLocation $action): JsonResponse
    {
        $this->allow('masterdata.manage');

        $action->handle($location);

        return ApiResponse::noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?AssetLocation $location): array
    {
        $unique = Rule::unique('asset_locations')->where('company_id', $this->context->companyId());

        if ($location !== null) {
            $unique = $unique->ignore($location->id);
        }

        return $request->validate([
            'factory_id' => ['required', 'string', 'size:26'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $unique],
            'building_id' => ['nullable', 'string', 'size:26'],
            'floor_id' => ['nullable', 'string', 'size:26'],
            'department_id' => ['nullable', 'string', 'size:26'],
            'section_id' => ['nullable', 'string', 'size:26'],
            'production_line_id' => ['nullable', 'string', 'size:26'],
            'workstation_id' => ['nullable', 'string', 'size:26'],
            'status' => ['nullable', Rule::in(['ACTIVE', 'INACTIVE'])],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(AssetLocation $location): array
    {
        return [
            'id' => $location->id,
            'factory_id' => $location->factory_id,
            'factory' => $location->relationLoaded('factory') ? [
                'id' => $location->factory?->id,
                'name' => $location->factory?->name,
                'code' => $location->factory?->code,
            ] : null,
            'name' => $location->name,
            'code' => $location->code,
            // Not shown on the list — needed so an edit form can preselect
            // these without a second round trip, and so re-saving without
            // touching them doesn't silently clear the association (each is
            // nullable at the validation layer).
            'building_id' => $location->building_id,
            'floor_id' => $location->floor_id,
            'department_id' => $location->department_id,
            'section_id' => $location->section_id,
            'production_line_id' => $location->production_line_id,
            'workstation_id' => $location->workstation_id,
            'full_path' => $location->full_path,
            'status' => $location->status,
            'asset_count' => $location->assets_count ?? null,
        ];
    }
}
