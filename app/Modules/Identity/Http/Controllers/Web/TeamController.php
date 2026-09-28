<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Web;

use App\Modules\Identity\Actions\ManageTeam;
use App\Modules\Identity\Models\Team;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Maintenance teams (SRS 25).
 *
 * A team is who a job is handed to when it is not handed to one person: the
 * night shift electricians, the dye house crew. Work orders, breakdowns,
 * maintenance plans, approval steps and escalation rules can all name one, and
 * until now none of them could, because nothing in the product created a team.
 *
 * The rules themselves live in ManageTeam (ADR-003), so the API builds and
 * retires a team under exactly the same rules as this screen.
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

    public function store(Request $request, ManageTeam $teams): RedirectResponse
    {
        $this->authorizeTeams($request);

        $teams->create($this->validated($request, null));

        return back()->with('status', __('team.created'));
    }

    public function update(Request $request, Team $team, ManageTeam $teams): RedirectResponse
    {
        $this->authorizeTeams($request);

        $teams->update($team, $this->validated($request, $team));

        return back()->with('status', __('team.updated'));
    }

    public function toggle(Request $request, Team $team, ManageTeam $teams): RedirectResponse
    {
        $this->authorizeTeams($request);

        $teams->setActive($team, $team->status !== 'ACTIVE');

        return back()->with('status', __('team.updated'));
    }

    public function destroy(Request $request, Team $team, ManageTeam $teams): RedirectResponse
    {
        $this->authorizeTeams($request);

        $teams->delete($team);

        return back()->with('status', __('team.deleted'));
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

        // Factory reachability is asserted inside ManageTeam itself, so both
        // this screen and the API refuse an unreachable factory the same way.
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $unique],
            'factory_id' => ['required', 'string', 'size:26'],
            'specialization' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function authorizeTeams(Request $request): void
    {
        if (! $request->user()->can('admin.team.manage')) {
            abort(403);
        }
    }
}
