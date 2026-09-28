<?php

declare(strict_types=1);

namespace App\Modules\Asset\Http\Controllers\Web;

use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

/**
 * The QR landing route (Data Dictionary 5.2, Frontend 4.9) — kept alive as
 * a redirect, not a page, so a label printed before this route was ported
 * still scans to somewhere real. A freshly generated label
 * (`AssetLabelApiController`) skips this hop and encodes the Next.js URL
 * directly.
 *
 * No auth check happens here on purpose: resolving the code, authorising
 * the viewer, and sending a guest to sign in and back are all now the
 * Next.js scan page's own job (`app/(app)/scan/[code]/page.js`) — doing it
 * twice, once on each side of the redirect, would mean a Blade session and
 * a Next.js session both have to be live for this to work, which is
 * exactly the double-login trap the old Blade landing page used to fall
 * into once "Report breakdown" sent a technician across the origin boundary.
 */
class ScanController extends Controller
{
    public function asset(string $code): RedirectResponse
    {
        return redirect($this->frontendPath('/scan/'.$code));
    }

    /**
     * Scanning a location lists what is currently standing there, which is
     * how a stock-take or an audit walk is performed (Data Dictionary 5.3).
     */
    public function location(string $code): RedirectResponse
    {
        return redirect($this->frontendPath('/scan/location/'.$code));
    }

    private function frontendPath(string $path): string
    {
        return rtrim((string) config('tenancy.frontend_url'), '/').$path;
    }
}
