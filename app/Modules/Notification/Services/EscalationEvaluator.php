<?php

declare(strict_types=1);

namespace App\Modules\Notification\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Notification\Models\EscalationRule;
use App\Modules\Notification\Models\Notification;
use App\Shared\Scopes\TenantScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Escalates notifications nobody has picked up (SRS 28).
 *
 * Two rules decide whether this is useful or merely noisy.
 *
 * Delay is measured from the original event, not from the previous escalation.
 * If each level waited from the one before it, a chain that stalls drifts: two
 * levels described as "thirty minutes later" become ninety when the first
 * escalation itself runs late, and the factory manager hears about a stopped
 * line an hour after they should have.
 *
 * Escalation stops on acknowledgement, not on read. Opening a list is not the
 * same as picking something up, and escalating past somebody who has already
 * said "I have this" wastes attention and teaches people to ignore the channel.
 */
class EscalationEvaluator
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    /**
     * Escalates everything that is due.
     *
     * @return array{escalated: int, examined: int, skipped_acknowledged: int}
     */
    public function run(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        $escalated = 0;
        $examined = 0;
        $skipped = 0;

        // Only chain heads. An escalation is escalated by looking at what it
        // came from, so walking the escalations themselves would double-count
        // the delay.
        $originals = Notification::whereNull('source_notification_id')
            ->whereNull('acknowledged_at')
            ->where('created_at', '>=', $now->subDays(7))
            ->get();

        foreach ($originals as $original) {
            $examined++;

            if ($original->isAcknowledged()) {
                $skipped++;

                continue;
            }

            $escalated += $this->escalate($original, $now);
        }

        return [
            'escalated' => $escalated,
            'examined' => $examined,
            'skipped_acknowledged' => $skipped,
        ];
    }

    /**
     * Escalates one notification as far as its rules and the clock allow.
     */
    public function escalate(Notification $original, ?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();

        $rules = $this->rulesFor($original);

        if ($rules->isEmpty()) {
            return 0;
        }

        $alreadyAt = Notification::where('source_notification_id', $original->id)
            ->max('escalation_level') ?? 0;

        $sent = 0;

        foreach ($rules as $rule) {
            if ($rule->escalation_level <= $alreadyAt) {
                continue;
            }

            if ($rule->escalation_level > $rule->max_escalations) {
                continue;
            }

            // From the original event. This is the whole point.
            $dueAt = CarbonImmutable::parse($original->created_at)->addMinutes($rule->delay_minutes);

            if ($now->lessThan($dueAt)) {
                // Not yet. Later levels have longer delays, so nothing beyond
                // this one is due either.
                break;
            }

            if ($rule->stop_on_acknowledge && $original->fresh()->isAcknowledged()) {
                break;
            }

            $recipients = $this->recipientsFor($rule, $original);

            if ($recipients->isEmpty()) {
                // A rule that reaches nobody is a configuration problem, not a
                // reason to skip ahead: escalating past it would quietly send
                // the company admin something the manager never saw.
                break;
            }

            foreach ($recipients as $recipient) {
                if ($recipient->id === $original->user_id) {
                    // Telling the same person again more loudly is not an
                    // escalation.
                    continue;
                }

                $this->dispatcher->send(
                    recipient: $recipient,
                    eventType: $original->event_type,
                    data: $original->data_json ?? [],
                    // An escalation is at least a warning: the thing it is
                    // about has now been ignored for a measured period.
                    severity: $original->severity === 'INFO' ? 'WARNING' : $original->severity,
                    factoryId: $original->factory_id,
                    entityType: $original->entity_type,
                    entityId: $original->entity_id,
                    actionUrl: $original->action_url,
                    escalationLevel: $rule->escalation_level,
                    // A new row, never a mutation of the original: the chain
                    // stays auditable and the first recipient's copy does not
                    // change owner (ERD Section 17 rule 3).
                    sourceNotificationId: $original->id,
                );

                $sent++;
            }
        }

        return $sent;
    }

    /**
     * @return Collection<int, EscalationRule>
     */
    private function rulesFor(Notification $notification): Collection
    {
        return EscalationRule::where('event_type', $notification->event_type)
            ->where('active', true)
            ->orderBy('escalation_level')
            ->get()
            ->filter(fn (EscalationRule $rule) => $rule->coversNotification($notification))
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function recipientsFor(EscalationRule $rule, Notification $notification): Collection
    {
        if ($rule->escalation_user_id !== null) {
            return User::where('id', $rule->escalation_user_id)->get();
        }

        if ($rule->escalation_role_id !== null) {
            $userIds = UserRole::withoutGlobalScope(TenantScope::class)
                ->where('company_id', $notification->company_id)
                ->where('role_id', $rule->escalation_role_id)
                // A factory-scoped holder of the role counts only for their own
                // factory; a company-wide holder counts everywhere.
                ->when(
                    $notification->factory_id !== null,
                    fn ($q) => $q->where(fn ($w) => $w->whereNull('factory_id')
                        ->orWhere('factory_id', $notification->factory_id)),
                )
                ->pluck('user_id')
                ->unique();

            return User::whereIn('id', $userIds)->where('status', 'ACTIVE')->get();
        }

        if ($rule->escalation_team_id !== null) {
            // Team membership lands with the teams workstream. Reaching for a
            // table that does not exist would throw the first time a team rule
            // fired, so this returns nobody — and an empty recipient list stops
            // the chain rather than skipping to the next level.
            return collect();
        }

        return collect();
    }
}
