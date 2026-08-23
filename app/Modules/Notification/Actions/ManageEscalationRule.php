<?php

declare(strict_types=1);

namespace App\Modules\Notification\Actions;

use App\Modules\Identity\Models\Role;
use App\Modules\Notification\Models\EscalationRule;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * Who gets told when nobody answers (SRS 28).
 *
 * The escalation engine has run since it was built, against rules a factory
 * could not write. This is the missing half: how long a critical breakdown may
 * sit unacknowledged before it goes past the person who ignored it.
 *
 * Rules name a role rather than a person, for the same reason approval chains
 * do — a rule that names Karim stops working the week Karim is on leave, and
 * that is precisely the week somebody needs it.
 */
class ManageEscalationRule
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): EscalationRule
    {
        $role = Role::availableTo($this->context->companyId())->find($data['escalation_role_id'] ?? null);

        if ($role === null) {
            throw ValidationException::withMessages([
                'escalation_role_id' => __('notification.unknown_role'),
            ]);
        }

        $factoryId = filled($data['factory_id'] ?? null) ? $data['factory_id'] : null;

        if ($factoryId !== null && ! $this->context->canAccessFactory($factoryId)) {
            throw ValidationException::withMessages([
                'factory_id' => __('notification.factory_unavailable'),
            ]);
        }

        $this->assertNoOverlap($data['event_type'], $data['severity'] ?? null, $factoryId, (int) $data['escalation_level']);

        return EscalationRule::create([
            'company_id' => $this->context->companyId(),
            'factory_id' => $factoryId,
            'event_type' => $data['event_type'],
            'severity' => filled($data['severity'] ?? null) ? $data['severity'] : null,
            'delay_minutes' => (int) $data['delay_minutes'],
            'escalation_level' => (int) $data['escalation_level'],
            'escalation_role_id' => $role->id,
            // Three by default, and never null: an escalation that never stops
            // climbing ends at somebody who can do nothing about it.
            'max_escalations' => filled($data['max_escalations'] ?? null) ? (int) $data['max_escalations'] : 3,
            // Almost always true: a rule that keeps escalating after somebody
            // has picked the job up teaches people to ignore the alerts.
            'stop_on_acknowledge' => (bool) ($data['stop_on_acknowledge'] ?? true),
            'active' => true,
        ]);
    }

    public function setActive(EscalationRule $rule, bool $active): EscalationRule
    {
        $rule->forceFill(['active' => $active])->save();

        return $rule->fresh();
    }

    public function delete(EscalationRule $rule): void
    {
        // Nothing points at a rule by foreign key — a notification records
        // where it went, not which rule sent it — so a rule that turned out to
        // be wrong can simply go.
        $rule->delete();
    }

    /**
     * Two rules at the same level for the same event would both fire, and the
     * same person would be told twice about one silence.
     */
    private function assertNoOverlap(string $eventType, ?string $severity, ?string $factoryId, int $level): void
    {
        $clash = EscalationRule::query()
            ->where('event_type', $eventType)
            ->where('escalation_level', $level)
            ->where(fn ($q) => $q->where('factory_id', $factoryId)->orWhereNull('factory_id'))
            ->when(
                filled($severity),
                fn ($q) => $q->where(fn ($inner) => $inner->where('severity', $severity)->orWhereNull('severity')),
            )
            ->exists();

        if ($clash) {
            throw ValidationException::withMessages([
                'escalation_level' => __('notification.level_already_covered', ['level' => $level]),
            ])->status(422);
        }
    }
}
