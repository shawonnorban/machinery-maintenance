<?php

declare(strict_types=1);

namespace App\Modules\Asset\Http\Controllers\Web;

use App\Modules\Asset\Actions\CreateAsset;
use App\Modules\Asset\Actions\UpdateAsset;
use App\Modules\Asset\Http\Requests\StoreAssetRequest;
use App\Modules\Asset\Http\Requests\UpdateAssetRequest;
use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetCategory;
use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Asset\Models\AssetType;
use App\Modules\Asset\Models\Manufacturer;
use App\Modules\Asset\Services\QrCodeRenderer;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Thin by design (ADR-003): authorize, validate, delegate, respond.
 */
class AssetController extends Controller
{
    /** Only these may be sorted on. An arbitrary column name would reach SQL. */
    private const SORTABLE = ['asset_code', 'name', 'status', 'criticality', 'created_at'];

    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Asset::class);

        $sort = in_array($request->query('sort'), self::SORTABLE, true)
            ? $request->query('sort')
            : 'created_at';

        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $assets = Asset::query()
            // Eager loaded because preventLazyLoading is on: an N+1 across a
            // 20,000-asset list is a production outage, not a slow page.
            ->with(['type:id,name', 'category:id,name', 'factory:id,name,code', 'location:id,name'])
            ->when($this->factoryScope($request), fn ($q, $id) => $q->where('current_factory_id', $id))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('criticality'), fn ($q, $v) => $q->where('criticality', $v))
            ->when($request->query('asset_type_id'), fn ($q, $v) => $q->where('asset_type_id', $v))
            ->when($request->string('search')->trim()->toString(), function ($q, string $term): void {
                // Prefix match so the index is usable. A leading wildcard would
                // force a full scan on every keystroke.
                $q->where(function ($q) use ($term): void {
                    $q->where('asset_code', 'like', $term.'%')
                        ->orWhere('name', 'like', $term.'%')
                        ->orWhere('serial_number', 'like', $term.'%');
                });
            })
            ->orderBy($sort, $direction)
            ->paginate(25)
            ->withQueryString();

        return view('asset::assets.index', [
            'assets' => $assets,
            'types' => AssetType::availableTo($this->context->companyId())->orderBy('name')->get(),
            'statuses' => Asset::STATUSES,
            'criticalities' => Asset::CRITICALITIES,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function show(Asset $asset, QrCodeRenderer $qr): View
    {
        $this->authorize('view', $asset);

        $asset->load([
            'type:id,name', 'category:id,name', 'manufacturer:id,name',
            'model:id,model', 'parent:id,asset_code,name',
            'factory:id,name,code', 'location:id,name,full_path,code',
            'children:id,parent_asset_id,asset_code,name,status',
        ]);

        return view('asset::assets.show', [
            'asset' => $asset,
            'statusHistory' => $asset->statusHistories()->limit(20)->get(),
            'transfers' => $asset->transfers()->with(['fromFactory:id,name', 'toFactory:id,name', 'toLocation:id,name'])->limit(20)->get(),
            'allowedTransitions' => Asset::TRANSITIONS[$asset->status] ?? [],
            'qrSvg' => $qr->inlineSvg(route('scan.asset', $asset->qr_code), 150),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Asset::class);

        return view('asset::assets.create', $this->formOptions());
    }

    public function store(StoreAssetRequest $request, CreateAsset $action): RedirectResponse
    {
        $asset = $action->handle($request->validated(), $request->user()->id);

        return redirect()
            ->route('app.assets.show', $asset)
            ->with('status', __('asset.created', ['code' => $asset->asset_code]));
    }

    public function edit(Asset $asset): View
    {
        $this->authorize('update', $asset);

        return view('asset::assets.edit', ['asset' => $asset] + $this->formOptions());
    }

    public function update(UpdateAssetRequest $request, Asset $asset, UpdateAsset $action): RedirectResponse
    {
        $action->handle($asset, $request->validated(), $request->user()->id);

        return redirect()
            ->route('app.assets.show', $asset)
            ->with('status', __('asset.updated', ['code' => $asset->asset_code]));
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        $companyId = $this->context->companyId();

        return [
            'types' => AssetType::availableTo($companyId)->where('active', true)->orderBy('name')->get(),
            'categories' => AssetCategory::availableTo($companyId)->where('active', true)->orderBy('name')->get(),
            'manufacturers' => Manufacturer::availableTo($companyId)->where('active', true)->orderBy('name')->get(),
            // Only factories the user can actually reach. Listing the rest
            // would leak the shape of the estate.
            'factories' => Factory::whereIn('id', $this->context->accessibleFactoryIds())->orderBy('name')->get(),
            'locations' => AssetLocation::whereIn('factory_id', $this->context->accessibleFactoryIds())
                ->where('status', 'ACTIVE')
                ->orderBy('name')
                ->get(),
            'criticalities' => Asset::CRITICALITIES,
            'creatableStatuses' => Asset::CREATABLE_STATUSES,
        ];
    }

    private function factoryScope(Request $request): ?string
    {
        $requested = $request->query('factory_id') ?: $this->context->factoryScopeId();

        return is_string($requested) && $this->context->canAccessFactory($requested)
            ? $requested
            : null;
    }
}
