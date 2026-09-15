<?php

declare(strict_types=1);

namespace App\Modules\Asset\Http\Controllers\Api;

use App\Modules\Asset\Actions\CreateAsset;
use App\Modules\Asset\Actions\DeleteAsset;
use App\Modules\Asset\Actions\UpdateAsset;
use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetCategory;
use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Asset\Models\AssetType;
use App\Modules\Asset\Models\Manufacturer;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Machines, over the wire (API 6; mirrors the web `AssetController`, which
 * this delegates every write to unchanged per ADR-003 — `CreateAsset`,
 * `UpdateAsset` and `DeleteAsset` carry every domain rule, including the
 * commissioning-workflow and history-retention checks this endpoint used to
 * defer entirely).
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

    /**
     * The index page's own KPI tile row — not scoped to whatever status/
     * criticality filter or page the list happens to be on, the same
     * reasoning as `WorkOrderApiController::counts()`.
     */
    public function counts(): JsonResponse
    {
        $this->allow('asset.asset.view_any');

        $factoryIds = $this->context->accessibleFactoryIds();
        $base = fn () => Asset::query()->whereIn('current_factory_id', $factoryIds);

        return ApiResponse::ok([
            'total' => $base()->count(),
            'running' => $base()->where('status', 'RUNNING')->count(),
            // A machine mid-repair and one merely flagged down both mean the
            // same thing to whoever is scanning this tile: not producing.
            'needs_attention' => $base()->whereIn('status', ['BREAKDOWN', 'UNDER_REPAIR'])->count(),
            'critical' => $base()->where('criticality', 'CRITICAL')->count(),
        ]);
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

    public function store(Request $request, CreateAsset $action): JsonResponse
    {
        $this->allow('asset.asset.create');

        $data = $this->validated($request, forCreate: true);

        try {
            $asset = $action->handle($data, $this->caller()->auditUserId());
        } catch (ValidationException $e) {
            throw ApiException::of(ErrorCode::VALIDATION_ERROR, implode(' ', $e->validator->errors()->all()), $e->errors());
        }

        return ApiResponse::created($this->detail($asset->fresh(['type', 'category', 'factory', 'location'])));
    }

    public function update(Request $request, Asset $asset, UpdateAsset $action): JsonResponse
    {
        $this->allow('asset.asset.update');
        $this->assertReachable($asset);

        // Mirrors AssetPolicy::update: a scrapped asset is history, and
        // editing it would rewrite the record of what was actually on the
        // floor. The action itself carries no such check — only the policy
        // does on the web side — so it is repeated here.
        if ($asset->status === 'SCRAPPED') {
            throw ApiException::of(ErrorCode::FORBIDDEN);
        }

        $data = $this->validated($request, forCreate: false);

        try {
            $updated = $action->handle($asset, $data, $this->caller()->auditUserId());
        } catch (ValidationException $e) {
            $status = $e->status ?? 422;
            $code = $status === 409 ? ErrorCode::CONFLICT : ErrorCode::VALIDATION_ERROR;

            throw ApiException::of($code, implode(' ', $e->validator->errors()->all()), $e->errors());
        }

        return ApiResponse::ok($this->detail($updated->fresh(['type', 'category', 'factory', 'location'])));
    }

    public function destroy(Asset $asset, DeleteAsset $action): JsonResponse
    {
        $this->allow('asset.asset.delete');
        $this->assertReachable($asset);

        try {
            $action->handle($asset);
        } catch (ValidationException $e) {
            throw ApiException::of(ErrorCode::CONFLICT, implode(' ', $e->validator->errors()->all()));
        }

        return ApiResponse::noContent();
    }

    /**
     * Everything the create/edit form's dropdowns need, bundled into one
     * call. Mirrors `AssetController::formOptions()` exactly — same
     * `availableTo()`/`active` scoping, same "only reachable factories"
     * rule for factories and locations — but reached over `asset.asset.
     * view_any` rather than `masterdata.manage`/`settings.factory.manage`.
     *
     * That distinction matters and was found, not assumed: `RoleSeeder`
     * grants `asset.asset.create` to MAINTENANCE_ENGINEER well below the
     * FACTORY_MANAGER tier that first gets `masterdata.manage`, so an
     * engineer creating a machine could open the web form fine (it reads
     * these models directly) but would have gotten a 403 from every
     * existing master-data/location/factory *read* endpoint, none of
     * which this create form should need `masterdata.manage` just to
     * populate its own dropdowns.
     */
    public function formOptions(): JsonResponse
    {
        $this->allow('asset.asset.view_any');

        $companyId = $this->context->companyId();
        $factoryIds = $this->context->accessibleFactoryIds();

        return ApiResponse::ok([
            'types' => AssetType::availableTo($companyId)->where('active', true)->orderBy('name')
                ->get(['id', 'name'])->all(),
            'categories' => AssetCategory::availableTo($companyId)->where('active', true)->orderBy('name')
                ->get(['id', 'name', 'asset_type_id'])->all(),
            'manufacturers' => Manufacturer::availableTo($companyId)->where('active', true)->orderBy('name')
                ->get(['id', 'name'])->all(),
            'factories' => Factory::whereIn('id', $factoryIds)->orderBy('name')->get(['id', 'name'])->all(),
            // `full_path` alongside the bare name — "Cutting Table 1" alone
            // reads as just another location, not a specific production
            // line, and the dropdown was the one place in the product that
            // showed a machine's line/department only after saving, never
            // while picking one.
            'locations' => AssetLocation::whereIn('factory_id', $factoryIds)->where('status', 'ACTIVE')
                ->orderBy('name')->get(['id', 'name', 'factory_id', 'full_path'])->all(),
            'criticalities' => Asset::CRITICALITIES,
        ]);
    }

    public function statusHistory(Asset $asset): JsonResponse
    {
        $this->allow('asset.asset.view');
        $this->assertReachable($asset);

        $history = $asset->statusHistories()->with('changedBy:id,name')->limit(100)->get();

        return ApiResponse::ok($history->map(fn ($h): array => [
            'id' => $h->id,
            'from_status' => $h->from_status,
            'to_status' => $h->to_status,
            'changed_by' => $h->changedBy === null ? null : ['id' => $h->changedBy->id, 'name' => $h->changedBy->name],
            'changed_at' => $h->changed_at?->toIso8601String(),
            'reason' => $h->reason,
            'source' => $h->source,
        ])->all());
    }

    /**
     * Not a mirror of anything on the web: `AssetController::show()` has no
     * per-asset work-order widget, and no Action or Service exists for this
     * either. Built directly against `WorkOrder`, the same ad hoc
     * `where('asset_id', ...)` pattern Costing's `AssetCostController` and
     * `MaintenanceHistoryReport` already use for the same question.
     */
    public function maintenanceHistory(Request $request, Asset $asset): JsonResponse
    {
        $this->allow('asset.asset.view');
        $this->assertReachable($asset);

        $orders = WorkOrder::where('asset_id', $asset->id)
            ->with(['maintenanceType:id,name'])
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return ApiResponse::paginated($orders, fn (WorkOrder $order): array => [
            'id' => $order->id,
            'work_order_number' => $order->work_order_number,
            'type' => $order->maintenanceType?->name,
            'status' => $order->status,
            'priority' => $order->priority,
            'scheduled_start' => $order->scheduled_start?->toIso8601String(),
            'completed_at' => $order->completed_at?->toIso8601String(),
        ]);
    }

    /**
     * @param  bool  $forCreate  Creation accepts `status` (restricted to
     *                           `CREATABLE_STATUSES`) and `asset_code`;
     *                           update accepts neither — both have their own
     *                           audited endpoint — and requires `version`
     *                           for optimistic locking (ADR-025).
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $forCreate): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'asset_type_id' => ['required', 'string', 'size:26'],
            'asset_category_id' => ['required', 'string', 'size:26'],
            'manufacturer_id' => ['nullable', 'string', 'size:26'],
            'asset_model_id' => ['nullable', 'string', 'size:26'],
            'parent_asset_id' => ['nullable', 'string', 'size:26'],
            'serial_number' => ['nullable', 'string', 'max:128'],
            'barcode' => ['nullable', 'string', 'max:64'],
            'country_of_origin' => ['nullable', 'string', 'size:2'],
            'criticality' => ['required', Rule::in(Asset::CRITICALITIES)],
            'current_factory_id' => ['required', 'string', 'size:26'],
            'asset_location_id' => ['required', 'string', 'size:26'],
            'purchase_date' => ['nullable', 'date'],
            'installation_date' => ['nullable', 'date'],
            'commissioning_date' => ['nullable', 'date'],
            'warranty_start' => ['nullable', 'date'],
            'warranty_end' => ['nullable', 'date'],
            'acquisition_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999999999'],
            'installation_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999999999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        if ($forCreate) {
            $rules['asset_code'] = ['required', 'string', 'max:64'];
            $rules['status'] = ['nullable', Rule::in(Asset::CREATABLE_STATUSES)];
        } else {
            $rules['version'] = ['required', 'integer', 'min:1'];
        }

        $data = $request->validate($rules);

        $hasCost = $request->filled('acquisition_cost') || $request->filled('installation_cost');

        if ($hasCost && ! $request->filled('currency')) {
            throw ValidationException::withMessages([
                'currency' => __('asset.currency_required_with_cost'),
            ]);
        }

        return $data;
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
            // Echoed on every response so a client always has the value its
            // next PATCH needs (ADR-025) without a separate round trip.
            'version' => $asset->version,
            'criticality' => $asset->criticality,
            'serial_number' => $asset->serial_number,
            // {id, name} rather than a bare name — the edit form (Next.js)
            // needs the id to preselect the dropdown; a name alone was fine
            // when nothing but display ever read this response.
            'type' => $asset->type === null ? null : ['id' => $asset->type->id, 'name' => $asset->type->name],
            'category' => $asset->category === null ? null : ['id' => $asset->category->id, 'name' => $asset->category->name],
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
            'manufacturer' => $asset->manufacturer === null ? null : ['id' => $asset->manufacturer->id, 'name' => $asset->manufacturer->name],
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
