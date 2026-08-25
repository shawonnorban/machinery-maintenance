<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Modules\Asset\Models\Asset;
use App\Modules\Billing\Models\SubscriptionContract;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * What a customer is entitled to, checked at the moment they try to exceed it.
 *
 * The limits have been on the contract since the billing module was written,
 * and until now nothing read them: included_factories could say 3 while the
 * customer ran 30. A number nobody enforces is not a limit, it is a note.
 *
 * Only BLOCK stops anything, which is UsageMeter's rule and not this class's.
 * WARN_ONLY and ALLOW_AND_BILL are commercial answers to going over — a mill
 * that commissions its 413th machine at 2am should not be stopped by a billing
 * rule nobody is awake to relax. Those two show up on the usage bars and on
 * the invoice instead.
 *
 * The counts here are deliberately the same ones UsageMeter measures. If the
 * guard counted assets differently from the meter, a customer could be blocked
 * at a number their own usage screen said they had not reached.
 */
class EntitlementGuard
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly UsageMeter $meter,
    ) {}

    /**
     * @throws ValidationException when the contract says BLOCK and the limit is reached
     */
    public function assertCanAdd(string $metric): void
    {
        $companyId = $this->context->companyId();

        if ($companyId === null) {
            return;
        }

        $contract = SubscriptionContract::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $companyId)
            ->whereNotIn('status', ['ARCHIVED', 'CANCELLED'])
            ->orderByDesc('start_date')
            ->first();

        // No contract is not a limit of zero. A customer still being set up has
        // no contract yet, and locking them out of their own system while
        // somebody in sales writes one would be absurd.
        if ($contract === null) {
            return;
        }

        $current = $this->currentCount($metric, $companyId);

        if (! $this->meter->wouldExceed($contract, $metric, $current)) {
            return;
        }

        // ValidationException on purpose: the web layout renders every message
        // in the bag, and the API renderer turns it into a 422. One throw
        // reaches both entry points correctly (ADR-066).
        throw ValidationException::withMessages([
            'limit' => __('billing.limit_reached_'.strtolower($metric), [
                'limit' => (string) $contract->limitFor($metric),
            ]),
        ]);
    }

    private function currentCount(string $metric, string $companyId): int
    {
        return match ($metric) {
            'ACTIVE_FACTORIES' => Factory::withoutGlobalScope(TenantScope::class)
                ->where('company_id', $companyId)
                ->where('status', 'ACTIVE')
                ->count(),

            // Retired and scrapped machines are excluded for the same reason
            // they are excluded from availability: they are not in service, and
            // a customer should not have to pay for a machine they have sold.
            'ACTIVE_ASSETS' => Asset::withoutGlobalScope(TenantScope::class)
                ->where('company_id', $companyId)
                ->whereNotIn('status', ['RETIRED', 'SCRAPPED', 'LOST', 'DRAFT'])
                ->count(),

            'ACTIVE_USERS' => CompanyUser::withoutGlobalScope(TenantScope::class)
                ->where('company_id', $companyId)
                ->where('status', 'ACTIVE')
                ->distinct()
                ->count('user_id'),

            default => 0,
        };
    }
}
