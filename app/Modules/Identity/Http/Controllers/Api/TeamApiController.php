<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api;

use App\Modules\Identity\Actions\ManageTeam;
use App\Modules\Identity\Models\Team;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Maintenance teams (API 4.1, SRS 25).
 *
 * Who a job is handed to when it is not handed to one person. The rules —
 * factory reachability, the "still in use" guard on delete — live in
 * ManageTeam (ADR-003), the same action the settings screen calls.
 *
 * `POST/DELETE /teams/{team}/members` from the spec are not implemented
 * here: nothing in the schema backs a team's membership today (Team carries
 * no members relationship, and no team_members table exists). Adding it is
 * a schema change, not a controller — tracked separately rather than faked
 * with a stub that would silently accept members nothing else could read.
 */
class TeamApiController extends ApiController
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * The one dropdown the create/edit form needs. `Factory` is gated on
     * `settings.factory.manage` everywhere else in the API, a permission
     * MAINTENANCE_MANAGER (who holds `admin.team.manage`, the permission
     * that actually creates a team) does not hold — `settings.factory.
     * manage` only starts at the FACTORY_MANAGER tier one level up. Same
     * shape of gap as Asset/Inventory/WorkOrder's create forms, closed the
     * same way: a lookup scoped to the permission that actually needs it.
     *
     * Registered ahead of `GET /teams/{team}` so the wildcard doesn't
     * swallow "form-options" as an attempted id.
     */
    public function formOptions(): JsonResponse
    {
        $this->allow('admin.team.manage');

        $factories = Factory::whereIn('id', $this->context->accessibleFactoryIds())
            ->orderBy('name')->get(['id', 'name']);

        return ApiResponse::ok(['factories' => $factories->all()]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->allow('admin.team.manage');

        $query = Team::query()
            ->with('factory:id,name')
            ->whereIn('factory_id', $this->context->accessibleFactoryIds());

        $query = $this->applyFilters($query, $request, ['status', 'factory_id']);
        $query = $this->applySort($query, $request, ['name', 'code'], 'name', 'asc');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (Team $team): array => $this->summary($team),
        );
    }

    public function store(Request $request, ManageTeam $teams): JsonResponse
    {
        $this->allow('admin.team.manage');

        $team = $teams->create($this->validated($request, null));

        return ApiResponse::created($this->summary($team->load('factory:id,name')));
    }

    public function show(Team $team, ManageTeam $teams): JsonResponse
    {
        $this->allow('admin.team.manage');
        $teams->assertReachable($team);

        return ApiResponse::ok($this->summary($team->load('factory:id,name')));
    }

    public function update(Request $request, Team $team, ManageTeam $teams): JsonResponse
    {
        $this->allow('admin.team.manage');

        $data = $this->validated($request, $team);

        $team = $teams->update($team, $data);

        if ($request->filled('status')) {
            $team = $teams->setActive($team, $request->string('status')->toString() === 'ACTIVE');
        }

        return ApiResponse::ok($this->summary($team->load('factory:id,name')));
    }

    public function destroy(Team $team, ManageTeam $teams): JsonResponse
    {
        $this->allow('admin.team.manage');

        $teams->delete($team);

        return ApiResponse::noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Team $team): array
    {
        return [
            'id' => $team->id,
            'name' => $team->name,
            'code' => $team->code,
            'specialization' => $team->specialization,
            'status' => $team->status,
            'factory' => ['id' => $team->factory_id, 'name' => $team->factory?->name],
        ];
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

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $unique],
            'factory_id' => ['required', 'string', 'size:26'],
            'specialization' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
