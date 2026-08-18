<?php

declare(strict_types=1);

namespace App\Modules\Asset\Http\Controllers\Web;

use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Services\ScanResolver;
use App\Shared\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * The QR landing route (Data Dictionary 5.2, Frontend 4.9).
 *
 * A scanned label carries a URL, so any phone camera opens it without the app
 * installed. Resolution still requires an authenticated session: the `auth`
 * middleware sends a guest to login and returns them here afterwards, which is
 * what a supervisor scanning a machine for the first time will hit.
 */
class ScanController extends Controller
{
    public function __construct(private readonly ScanResolver $resolver) {}

    public function asset(string $code, ScanResolver $resolver): View
    {
        $asset = $this->resolver->asset($code);

        // 404 whether the token never existed or belongs to another tenant.
        // A scanned label must not be usable to probe for foreign assets.
        abort_if($asset === null, 404);

        $this->authorize('view', $asset);

        return view('asset::scan.asset', [
            'asset' => $asset,
            'actions' => $this->resolver->actionsFor($asset),
        ]);
    }

    /**
     * Scanning a location lists what is currently standing there, which is how
     * a stock-take or an audit walk is performed (Data Dictionary 5.3).
     */
    public function location(string $code): View
    {
        $location = $this->resolver->location($code);

        abort_if($location === null, 404);

        $this->authorize('viewAny', Asset::class);

        return view('asset::scan.location', [
            'location' => $location,
            'assets' => Asset::query()
                ->with(['type:id,name'])
                ->where('asset_location_id', $location->id)
                ->orderBy('asset_code')
                ->get(),
        ]);
    }
}
