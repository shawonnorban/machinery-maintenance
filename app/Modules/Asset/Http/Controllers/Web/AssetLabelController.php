<?php

declare(strict_types=1);

namespace App\Modules\Asset\Http\Controllers\Web;

use App\Modules\Asset\Actions\RegenerateQrToken;
use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Services\QrCodeRenderer;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssetLabelController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly QrCodeRenderer $qr,
    ) {}

    /**
     * The label sheet. A label carries the QR, the asset code and the asset
     * name, and nothing else (Data Dictionary 5.5): anything more is one more
     * thing to go stale on a sticker nobody reprints.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Asset::class);

        $selected = array_filter((array) $request->query('ids', []));

        $assets = Asset::query()
            ->with(['location:id,name'])
            ->when($selected !== [], fn ($q) => $q->whereIn('id', $selected))
            ->when($this->factoryScope($request), fn ($q, $id) => $q->where('current_factory_id', $id))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderBy('asset_code')
            // Bounded: a tenant with 20,000 assets asking for every label at
            // once would render a document no printer will accept.
            ->limit(200)
            ->get();

        return view('asset::labels.sheet', [
            'assets' => $assets,
            'labels' => $assets->mapWithKeys(fn (Asset $asset) => [
                $asset->id => $this->qr->inlineSvg(route('scan.asset', $asset->qr_code), 160),
            ]),
            'truncated' => $assets->count() === 200,
        ]);
    }

    /**
     * Regenerating invalidates the printed label, so it is audited and gated
     * behind its own permission (Data Dictionary 5.5).
     */
    public function regenerate(Asset $asset, RegenerateQrToken $action): RedirectResponse
    {
        $this->authorize('regenerateQr', $asset);

        $action->handle($asset, request()->user()->id);

        return back()->with('status', __('scan.qr_regenerated', ['code' => $asset->fresh()->qr_code]));
    }

    private function factoryScope(Request $request): ?string
    {
        $requested = $request->query('factory_id') ?: $this->context->factoryScopeId();

        return is_string($requested) && $this->context->canAccessFactory($requested)
            ? $requested
            : null;
    }
}
