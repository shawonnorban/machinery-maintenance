<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers\Web;

use App\Modules\Tenancy\Http\Middleware\ResolveTenantContext;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The two global scope controls in the header (Frontend 4.2).
 */
class PreferenceController extends Controller
{
    public function factoryScope(Request $request, TenantContext $context): RedirectResponse
    {
        $validated = $request->validate([
            'factory_id' => ['nullable', 'string', 'size:26'],
        ]);

        $factoryId = $validated['factory_id'] ?? null;

        // A factory the user cannot reach is dropped rather than applied. The
        // filter narrows results; it can never widen them (SRS 4).
        if ($factoryId !== null && ! $context->canAccessFactory($factoryId)) {
            $factoryId = null;
        }

        $request->session()->put(ResolveTenantContext::FACTORY_SCOPE_KEY, $factoryId);

        return back();
    }

    public function locale(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:en,bn'],
        ]);

        $request->user()->forceFill(['locale' => $validated['locale']])->save();

        return back();
    }
}
