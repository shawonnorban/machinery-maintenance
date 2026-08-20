<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Billing\Models\SubscriptionContract;
use App\Modules\Billing\Models\SubscriptionInvoice;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * How a subscription moves between states (ADR-029, SRS 40, SRS 49.3).
 *
 * Active → Past due → Grace → Read only → Archived, with cancellation possible
 * from anywhere. The shape matters more than any single transition: a factory
 * that misses a payment does not lose its maintenance history, it loses the
 * ability to write to it, and even then every screen and every export still
 * works so the customer can take their data with them.
 *
 * Nothing here deletes anything, ever. Automatic hard deletion on payment
 * failure is prohibited (SRS 49.3), and archiving is as far as this class goes.
 */
class SubscriptionLifecycle
{
    /**
     * What may follow what.
     *
     * READ_ONLY can go back to ACTIVE: a customer who pays what they owe gets
     * their system back, and a lifecycle that only narrows would make paying up
     * pointless.
     */
    private const TRANSITIONS = [
        'DRAFT' => ['TRIAL', 'ACTIVE', 'CANCELLED'],
        'TRIAL' => ['ACTIVE', 'PAST_DUE', 'CANCELLED'],
        'ACTIVE' => ['PAST_DUE', 'CANCELLED', 'ARCHIVED'],
        'PAST_DUE' => ['ACTIVE', 'GRACE', 'CANCELLED'],
        'GRACE' => ['ACTIVE', 'READ_ONLY', 'CANCELLED'],
        'READ_ONLY' => ['ACTIVE', 'ARCHIVED', 'CANCELLED'],
        'CANCELLED' => ['READ_ONLY', 'ARCHIVED'],
        'ARCHIVED' => [],
    ];

    public function __construct(private readonly AuditRecorder $audit) {}

    public function transition(
        SubscriptionContract $contract,
        string $to,
        ?string $reason = null,
        ?string $userId = null,
    ): SubscriptionContract {
        $from = $contract->status;

        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => __('billing.transition_invalid', ['from' => $from, 'to' => $to]),
            ])->status(409);
        }

        $now = CarbonImmutable::now();

        $attributes = ['status' => $to];

        // Each timestamp is set by the transition that earns it, and cleared
        // only by coming back to ACTIVE: paying up must actually give the system
        // back rather than leaving a stale timestamp a later check reads as
        // still restricted.
        match ($to) {
            'READ_ONLY' => $attributes['read_only_at'] = $now,
            'ARCHIVED' => $attributes['archived_at'] = $now,
            'CANCELLED' => $attributes = $attributes + [
                'cancelled_at' => $now,
                'cancellation_reason' => $reason,
            ],
            'ACTIVE' => $attributes['read_only_at'] = null,
            default => null,
        };

        $contract->forceFill($attributes)->save();

        // Lifecycle transitions are audited (ERD Section 19): a customer locked
        // out of writes must be able to be told exactly when and why.
        $this->audit->event(
            'SUBSCRIPTION_CHANGED',
            ['from' => $from, 'to' => $to, 'reason' => $reason],
            companyId: $contract->company_id,
            userId: $userId,
            label: $contract->contract_number,
        );

        return $contract->fresh();
    }

    /**
     * Move one contract to wherever the calendar says it should be.
     *
     * Driven by unpaid invoices and dates rather than by anybody remembering.
     * The scheduled command calls this for every contract; it returns the state
     * it left the contract in.
     */
    public function advance(SubscriptionContract $contract, ?CarbonImmutable $now = null): string
    {
        $now ??= CarbonImmutable::now();

        // A cancelled or archived contract is where the customer or the
        // platform decided it should be. The calendar does not overrule that.
        if (in_array($contract->status, ['ARCHIVED', 'CANCELLED', 'DRAFT'], true)) {
            return $contract->status;
        }

        if ($contract->status === 'TRIAL' && $contract->trial_end !== null && $contract->trial_end->lt($now)) {
            return $this->transition($contract, 'ACTIVE', 'Trial ended')->status;
        }

        $overdue = $this->oldestOverdueInvoice($contract, $now);

        if ($overdue === null) {
            // Everything paid. A contract sitting in PAST_DUE or GRACE after
            // the money arrived must come back, or paying achieves nothing.
            return in_array($contract->status, ['PAST_DUE', 'GRACE', 'READ_ONLY'], true)
                ? $this->transition($contract, 'ACTIVE', 'All invoices settled')->status
                : $contract->status;
        }

        $daysLate = (int) $overdue->due_date->startOfDay()->diffInDays($now->startOfDay(), absolute: false);

        // The grace period is measured from the due date of the oldest unpaid
        // invoice, not from when somebody noticed.
        return match (true) {
            $daysLate > $contract->grace_period_days && $contract->status !== 'READ_ONLY' => $this->narrow($contract, $now),
            $daysLate > 0 && $contract->status === 'ACTIVE' => $this->transition($contract, 'PAST_DUE', "Invoice {$overdue->invoice_number} is overdue")->status,
            $daysLate > 0 && $contract->status === 'PAST_DUE' => $this->transition($contract, 'GRACE', "Grace period for {$overdue->invoice_number}")->status,
            default => $contract->status,
        };
    }

    /**
     * Walk a contract down to READ_ONLY through whatever states are in the way.
     *
     * A contract can be several steps behind if the scheduler was not running,
     * and skipping states would leave the audit trail claiming the customer
     * went from ACTIVE to READ_ONLY overnight.
     */
    private function narrow(SubscriptionContract $contract, CarbonImmutable $now): string
    {
        foreach (['PAST_DUE', 'GRACE', 'READ_ONLY'] as $step) {
            if ($contract->status === $step) {
                continue;
            }

            if (! in_array($step, self::TRANSITIONS[$contract->status] ?? [], true)) {
                continue;
            }

            $contract = $this->transition($contract, $step, 'Grace period expired');
        }

        return $contract->status;
    }

    private function oldestOverdueInvoice(SubscriptionContract $contract, CarbonImmutable $now): ?SubscriptionInvoice
    {
        return SubscriptionInvoice::query()
            ->where('subscription_contract_id', $contract->id)
            ->whereIn('status', SubscriptionInvoice::OPEN_STATUSES)
            ->whereDate('due_date', '<', $now->toDateString())
            ->orderBy('due_date')
            ->first();
    }
}
