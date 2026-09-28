<?php

declare(strict_types=1);

namespace App\Modules\Asset\Http\Controllers\Web;

use App\Modules\Asset\Actions\DeleteAssetLocation;
use App\Modules\Asset\Actions\SaveAssetLocation;
use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Department;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\ProductionLine;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Where machines live (ADR-052).
 *
 * The other half of what a new tenant needs before it can register anything:
 * an asset names a location, and until now the only way to create one was a
 * spreadsheet import.
 */
class AssetLocationController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): View
    {
        $this->authorizeSettings($request);

        $locations = AssetLocation::query()
            ->with(['factory:id,name,code', 'productionLine:id,name'])
            ->withCount('assets')
            ->when($request->query('factory_id'), fn ($q, $id) => $q->where('factory_id', $id))
            ->when($request->string('search')->trim()->toString(), function ($q, string $term): void {
                $q->where(fn ($w) => $w->where('code', 'like', $term.'%')
                    ->orWhere('name', 'like', $term.'%'));
            })
            ->orderBy('code')
            ->paginate(min(max((int) $request->query('per_page', 25), 10), 100))
            ->withQueryString();

        return view('asset::locations.index', [
            'locations' => $locations,
            'factories' => $this->factories(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeSettings($request);

        return view('asset::locations.form', $this->formOptions() + ['location' => null]);
    }

    public function store(Request $request, SaveAssetLocation $action): RedirectResponse
    {
        $this->authorizeSettings($request);

        $location = $action->create($this->validated($request, null));

        return redirect()
            ->route('app.settings.locations')
            ->with('status', __('asset.location_created', ['code' => $location->code]));
    }

    public function edit(Request $request, AssetLocation $location): View
    {
        $this->authorizeSettings($request);

        return view('asset::locations.form', $this->formOptions() + ['location' => $location]);
    }

    public function update(Request $request, AssetLocation $location, SaveAssetLocation $action): RedirectResponse
    {
        $this->authorizeSettings($request);

        $action->update($location, $this->validated($request, $location));

        return redirect()
            ->route('app.settings.locations')
            ->with('status', __('asset.location_updated', ['code' => $location->code]));
    }

    /**
     * Close a location, or reopen it.
     *
     * A closed location keeps every machine that ever stood in it readable; it
     * simply stops being offered when somebody registers or moves one.
     */
    public function toggle(Request $request, AssetLocation $location, SaveAssetLocation $action): RedirectResponse
    {
        $this->authorizeSettings($request);

        $action->setStatus($location, $location->status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE');

        return back()->with('status', __('asset.location_updated', ['code' => $location->code]));
    }

    public function destroy(Request $request, AssetLocation $location, DeleteAssetLocation $action): RedirectResponse
    {
        $this->authorizeSettings($request);

        $code = $location->code;

        $action->handle($location);

        return redirect()
            ->route('app.settings.locations')
            ->with('status', __('asset.location_deleted', ['code' => $code]));
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
            'department_id' => ['nullable', 'string', 'size:26'],
            'production_line_id' => ['nullable', 'string', 'size:26'],
            'status' => ['nullable', Rule::in(['ACTIVE', 'INACTIVE'])],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'factories' => $this->factories(),
            // The optional levels, offered only where a company has modelled
            // them. A picker of empty dropdowns invites people to invent a
            // hierarchy they do not use.
            'buildings' => Building::orderBy('name')->get(),
            'departments' => Department::orderBy('name')->get(),
            'lines' => ProductionLine::orderBy('name')->get(),
        ];
    }

    /**
     * Only factories this user can actually reach: listing the rest would leak
     * the shape of the estate.
     */
    private function factories()
    {
        return Factory::whereIn('id', $this->context->accessibleFactoryIds())
            ->orderBy('name')
            ->get();
    }

    private function authorizeSettings(Request $request): void
    {
        if (! $request->user()->can('masterdata.manage')) {
            abort(403);
        }
    }
}
