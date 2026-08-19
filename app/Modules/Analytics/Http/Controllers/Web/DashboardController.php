<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Http\Controllers\Web;

use App\Modules\Analytics\Services\DashboardData;
use App\Modules\Tenancy\Http\Middleware\ResolveTenantContext;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The dashboard (SRS 30).
 *
 * Which panels a person sees is decided by what they can act on, not by a
 * setting. A storekeeper opening the app should land on stock, not on a fleet
 * availability figure they cannot influence.
 */
class DashboardController extends Controller
{
    /** Longest period offered. A year of raw scanning is a report, not a tile. */
    private const PERIODS = [7, 30, 90];

    public function __construct(
        private readonly TenantContext $context,
        private readonly DashboardData $data,
    ) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $days = in_array((int) $request->query('days'), self::PERIODS, true)
            ? (int) $request->query('days')
            : 30;

        $to = CarbonImmutable::now();
        $from = $to->subDays($days)->startOfDay();

        // The global factory scope, not a per-page filter (Frontend 4.2).
        $factoryId = session(ResolveTenantContext::FACTORY_SCOPE_KEY);

        $canSeeManagement = $user->can('dashboard.management.view');
        $canSeeMaintenance = $user->can('dashboard.maintenance.view');
        $canSeeStore = $user->can('dashboard.store.view');

        return view('analytics::dashboard.index', [
            'company' => Company::find($this->context->companyId()),
            'days' => $days,
            'periods' => self::PERIODS,
            'from' => $from,
            'to' => $to,
            'factoryId' => $factoryId,

            'canSeeManagement' => $canSeeManagement,
            'canSeeMaintenance' => $canSeeMaintenance,
            'canSeeStore' => $canSeeStore,

            // Only what will actually be rendered: a storekeeper should not pay
            // for a fleet-wide availability scan on every page load.
            'management' => $canSeeManagement ? $this->data->management($from, $to, $factoryId) : null,
            'maintenance' => $canSeeMaintenance ? $this->data->maintenance($from, $to, $factoryId) : null,
            'store' => $canSeeStore ? $this->data->store($from, $to) : null,
        ]);
    }
}
