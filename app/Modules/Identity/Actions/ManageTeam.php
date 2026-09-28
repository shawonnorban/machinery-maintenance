<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Identity\Models\Team;
use App\Modules\Maintenance\Models\MaintenancePlan;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * Maintenance teams (SRS 25).
 *
 * A team is who a job is handed to when it is not handed to one person: the
 * night shift electricians, the dye house crew. Work orders, breakdowns,
 * maintenance plans, approval steps and escalation rules can all name one.
 *
 * Pulled out of the web controller (ADR-003) so the API can create, update
 * and retire a team through the same rules rather than a second copy of them.
 */
class ManageTeam
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  array{name: string, code: string, factory_id: string, specialization?: ?string}  $data
     */
    public function create(array $data): Team
    {
        $this->assertFactoryReachable($data['factory_id']);

        return Team::create([
            'company_id' => $this->context->companyId(),
            'name' => $data['name'],
            'code' => $this->normalizeCode($data['code']),
            'factory_id' => $data['factory_id'],
            'specialization' => $data['specialization'] ?? null,
            'status' => 'ACTIVE',
        ]);
    }

    /**
     * @param  array{name: string, code: string, factory_id: string, specialization?: ?string}  $data
     */
    public function update(Team $team, array $data): Team
    {
        $this->assertReachable($team);
        $this->assertFactoryReachable($data['factory_id']);

        $team->update([
            'name' => $data['name'],
            'code' => $this->normalizeCode($data['code']),
            'factory_id' => $data['factory_id'],
            'specialization' => $data['specialization'] ?? null,
        ]);

        return $team->fresh();
    }

    public function setActive(Team $team, bool $active): Team
    {
        $this->assertReachable($team);

        $team->forceFill(['status' => $active ? 'ACTIVE' : 'INACTIVE'])->save();

        return $team->fresh();
    }

    public function delete(Team $team): void
    {
        $this->assertReachable($team);

        $assigned = $this->assignmentCount($team);

        if ($assigned > 0) {
            throw ValidationException::withMessages([
                'name' => __('team.in_use', ['count' => $assigned]),
            ])->status(409);
        }

        $team->delete();
    }

    public function assertReachable(Team $team): void
    {
        if (! $this->context->canAccessFactory((string) $team->factory_id)) {
            abort(404);
        }
    }

    public function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
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

    private function assertFactoryReachable(string $factoryId): void
    {
        if (! $this->context->canAccessFactory($factoryId)) {
            throw ValidationException::withMessages([
                'factory_id' => __('team.factory_unavailable'),
            ]);
        }
    }
}
