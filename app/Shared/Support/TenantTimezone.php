<?php

declare(strict_types=1);

namespace App\Shared\Support;

use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;

/**
 * The timezone the interface speaks in (SRS 47.2).
 *
 * Everything is stored in UTC, which is correct and non-negotiable: a company
 * with factories in two zones cannot have a single local clock, and DST would
 * make stored local times ambiguous twice a year.
 *
 * But a supervisor in Dhaka typing "21:50" into a form means 21:50 in Dhaka. If
 * that is parsed as UTC it lands six hours away, and every downtime, response
 * and repair figure derived from it is wrong by six hours while looking
 * completely plausible. This class is the boundary where wall time becomes an
 * instant and back again.
 *
 * Resolution order: the user's own setting, then the factory they are scoped
 * to, then the company, then the application default. The user wins because a
 * manager reviewing a Gazipur breakdown from Dhaka wants their own clock.
 */
class TenantTimezone
{
    private ?string $cached = null;

    public function __construct(private readonly TenantContext $context) {}

    public function current(): string
    {
        if ($this->cached !== null) {
            return $this->cached;
        }

        return $this->cached = $this->resolve();
    }

    /**
     * Wall time as the user typed it, converted to the UTC instant it names.
     */
    public function toUtc(string $localDateTime, ?string $timezone = null): CarbonImmutable
    {
        return CarbonImmutable::parse($localDateTime, $timezone ?? $this->current())
            ->setTimezone('UTC');
    }

    /**
     * A stored instant rendered on the reader's clock.
     */
    public function toLocal(CarbonInterface $utc, ?string $timezone = null): CarbonImmutable
    {
        return CarbonImmutable::parse($utc)->setTimezone($timezone ?? $this->current());
    }

    /** For display. Returns null rather than an empty string so views can branch. */
    public function format(?CarbonInterface $utc, string $format = 'Y-m-d H:i'): ?string
    {
        return $utc === null ? null : $this->toLocal($utc)->format($format);
    }

    /** For a datetime-local input's value attribute. */
    public function forInput(?CarbonInterface $utc): ?string
    {
        return $this->format($utc, 'Y-m-d\TH:i');
    }

    /** Cleared when the tenant context changes, so a switch is not stale. */
    public function forget(): void
    {
        $this->cached = null;
    }

    private function resolve(): string
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user !== null && filled($user->timezone)) {
            return $user->timezone;
        }

        $factoryId = $this->context->factoryScopeId();

        if ($factoryId !== null) {
            $factory = Factory::withoutGlobalScopes()->find($factoryId);

            if ($factory !== null && filled($factory->timezone)) {
                return $factory->timezone;
            }
        }

        $companyId = $this->context->companyIdOrNull();

        if ($companyId !== null) {
            $company = Company::withoutGlobalScopes()->find($companyId);

            if ($company !== null && filled($company->timezone)) {
                return $company->timezone;
            }
        }

        return config('app.display_timezone', 'Asia/Dhaka');
    }
}
