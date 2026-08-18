<?php

declare(strict_types=1);

namespace App\Modules\Asset\Http\Controllers\Web;

use App\Modules\Asset\Actions\TransferAsset;
use App\Modules\Asset\Http\Requests\TransferAssetRequest;
use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Asset\Models\AssetTransfer;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AssetTransferController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    /** The pending queue: what is waiting on somebody. */
    public function index(): View
    {
        $this->authorize('viewAny', Asset::class);

        return view('asset::transfers.index', [
            'transfers' => AssetTransfer::query()
                ->with(['asset:id,asset_code,name', 'fromFactory:id,name', 'toFactory:id,name', 'toLocation:id,name'])
                ->whereIn('status', ['REQUESTED', 'APPROVED', 'IN_TRANSIT'])
                ->orderByDesc('requested_at')
                ->paginate(25),
        ]);
    }

    public function create(Asset $asset): View
    {
        $this->authorize('transfer', $asset);

        $asset->load(['factory:id,name', 'location:id,name,full_path']);

        return view('asset::transfers.create', [
            'asset' => $asset,
            'locations' => AssetLocation::query()
                ->whereIn('factory_id', $this->context->accessibleFactoryIds())
                ->where('status', 'ACTIVE')
                ->whereKeyNot($asset->asset_location_id)
                ->with('factory:id,name')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(TransferAssetRequest $request, Asset $asset, TransferAsset $action): RedirectResponse
    {
        if ((int) $request->integer('version') !== $asset->version) {
            throw ValidationException::withMessages([
                'version' => __('asset.version_conflict', [
                    'current' => $asset->version,
                    'submitted' => $request->integer('version'),
                ]),
            ])->status(409);
        }

        $destination = AssetLocation::findOrFail($request->string('to_location_id')->toString());

        $transfer = $action->request(
            asset: $asset,
            toLocationId: $destination->id,
            reason: $request->string('reason')->toString(),
            userId: $request->user()->id,
            notes: $request->string('notes')->toString() ?: null,
            // A move inside one factory needs no approval hop; a move between
            // factories does, because two custodians are involved.
            autoReceive: $destination->factory_id === $asset->current_factory_id,
        );

        return redirect()
            ->route('app.assets.show', $asset)
            ->with('status', __('asset.transfer_'.strtolower($transfer->status), [
                'number' => $transfer->transfer_number,
            ]));
    }

    public function approve(AssetTransfer $transfer, TransferAsset $action): RedirectResponse
    {
        $asset = Asset::findOrFail($transfer->asset_id);
        $this->authorize('approveTransfer', $asset);

        $action->approve($transfer, request()->user()->id);

        return back()->with('status', __('asset.transfer_approved', ['number' => $transfer->transfer_number]));
    }

    public function receive(AssetTransfer $transfer, TransferAsset $action): RedirectResponse
    {
        $asset = Asset::findOrFail($transfer->asset_id);
        $this->authorize('approveTransfer', $asset);

        $action->receive($transfer, request()->user()->id);

        return back()->with('status', __('asset.transfer_received', ['number' => $transfer->transfer_number]));
    }

    public function reject(AssetTransfer $transfer, TransferAsset $action): RedirectResponse
    {
        $asset = Asset::findOrFail($transfer->asset_id);
        $this->authorize('approveTransfer', $asset);

        $reason = request()->validate(['rejection_reason' => ['required', 'string', 'max:255']]);

        $action->reject($transfer, request()->user()->id, $reason['rejection_reason']);

        return back()->with('status', __('asset.transfer_rejected', ['number' => $transfer->transfer_number]));
    }
}
