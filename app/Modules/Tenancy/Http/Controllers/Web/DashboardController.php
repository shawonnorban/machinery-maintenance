<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers\Web;

use App\Modules\Identity\Services\PermissionResolver;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\View\View;

/**
 * Placeholder dashboard until the Analytics module lands (build order 18).
 *
 * The KPI tiles deliberately render N/A rather than 0: a manager acting on a
 * fabricated zero is worse than one seeing that the figure is not available
 * yet (SRS 31.2 rule 2).
 */
class DashboardController extends Controller
{
    public function __invoke(TenantContext $context, PermissionResolver $permissions): View
    {
        $user = auth()->user();

        // The shell's copy of $factories comes from AppShellComposer and is
        // bound to the layout, not to this view. Page data is the page's own
        // responsibility.
        return view('dashboard.index', [
            'company' => Company::find($context->companyId()),
            'factories' => Factory::query()
                ->whereIn('id', $context->accessibleFactoryIds())
                ->orderBy('name')
                ->get(),
            'assetCount' => 0,
            'permissionCount' => count($permissions->permissionsFor($user, $context->companyId())),
        ]);
    }
}
