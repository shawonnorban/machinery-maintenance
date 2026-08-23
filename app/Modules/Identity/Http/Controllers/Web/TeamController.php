<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Web;

use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Identity\Models\Team;
use App\Modules\Maintenance\Models\MaintenancePlan;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Maintenance teams (SRS 25).
 *
 * A team is who a job is handed to when it is not handed to one person: the
 * night shift electricians, the dye house crew. Work orders, breakdowns,
 * maintenance plans, approval steps and escalation rules can all name one, and
 * until now none of them could, because nothing in the product created a team.
 */
class TeamController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): View
    {
        $this->authorizeTeams($request);

        return view('identity::teams.index', [
            'teams' => Team::query()
                ->with('factory:id,name')
                ->whereIn('factory_id', $this->context->accessibleFactoryIds())
                ->orderBy('name')
                ->get(),
            'factories' => Factory::whereIn('id', $this->context->accessibleFactoryIds())
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeTeams($request);

        $data = $this->validated($request, null);

        Team::create($data + [
            'company_id' => $this->context->companyId(),
            'status' => 'ACTIVE',
        ]);

        return back()->with('status', __('team.created'));
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        $this->authorizeTeams($request);
        $this->assertReachable($team);

        $team->update($this->validated($request, $team));

        return back()->with('status', __('team.updated'));
    }

    public function toggle(Request $request, Team $team): RedirectResponse
    {
        $this->authorizeTeams($request);
        $this->assertReachable($team);

        $team->forceFill(['status' => $team->status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE'])->save();

        return back()->with('status', __('team.updated'));
    }

    public function destroy(Request $request, Team $team): RedirectResponse
    {
        $this->authorizeTeams($request);
        $this->assertReachable($team);

        $assigned = $this->assignmentCount($team);

        if ($assigned > 0) {
            throw ValidationException::withMessages([
                'name' => __('team.in_use', ['count' => $assigned]),
            ])->status(409);
        }

        $team->delete();

        return back()->with('status', __('team.deleted'));
    }

    /**
     * Everything that can name a team. A job still has to say who it went to.
     */
    private function assignmentCount(Team $team): int
    {
        $total = 0;

        foreach ([WorkOrder::class, Breakdown::class, MaintenancePlan::class] as $model) {
            $total += $model::query()
                ->withoutGlobalScope(TenantScope::class)
                ->where('assigned_team_id', $team->id)
                ->count();
        }

        return $total;
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Team $team): array
    {
        $unique = Rule::unique('teams', 'code')
            ->where(fn ($q) => $q->where('company_id', $this->context->companyId()));

        if ($team !== null) {
            $unique = $unique->ignore($team->id);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $unique],
            'factory_id' => ['required', 'string', 'size:26'],
            'specialization' => ['nullable', 'string', 'max:255'],
        ]);

        if (! $this->context->canAccessFactory($data['factory_id'])) {
            throw ValidationException::withMessages([
                'factory_id' => __('team.factory_unavailable'),
            ]);
        }

        $data['code'] = strtoupper(trim($data['code']));

        return $data;
    }

    private function assertReachable(Team $team): void
    {
        if (! $this->context->canAccessFactory((string) $team->factory_id)) {
            abort(404);
        }
    }

    private function authorizeTeams(Request $request): void
    {
        if (! $request->user()->can('admin.team.manage')) {
            abort(403);
        }
    }
}
