<?php

declare(strict_types=1);

namespace App\Shared\Tenancy;

use App\Shared\Exceptions\TenantContextMissingException;

/**
 * Holds the resolved tenant context for the current request or job.
 *
 * The company id is derived from authenticated membership and never from a
 * client-supplied value (SRS 4, ADR-042 rule 1, ADR-064). Nothing outside
 * ResolveTenantContext middleware and the queue context restorer may call set().
 */
class TenantContext
{
    private ?string $companyId = null;

    /** @var list<string> */
    private array $accessibleFactoryIds = [];

    private ?string $factoryScopeId = null;

    private bool $resolved = false;

    /**
     * @param  list<string>  $accessibleFactoryIds
     */
    public function set(string $companyId, array $accessibleFactoryIds = []): void
    {
        $this->companyId = $companyId;
        $this->accessibleFactoryIds = array_values($accessibleFactoryIds);
        $this->resolved = true;
    }

    public function companyId(): string
    {
        if (! $this->resolved || $this->companyId === null) {
            throw new TenantContextMissingException(
                'Tenant context was not resolved. A tenant-scoped query ran outside a request '
                .'with a company, or a queued job did not restore its context.',
            );
        }

        return $this->companyId;
    }

    public function companyIdOrNull(): ?string
    {
        return $this->companyId;
    }

    public function hasContext(): bool
    {
        return $this->resolved && $this->companyId !== null;
    }

    /**
     * @return list<string>
     */
    public function accessibleFactoryIds(): array
    {
        return $this->accessibleFactoryIds;
    }

    public function canAccessFactory(string $factoryId): bool
    {
        return in_array($factoryId, $this->accessibleFactoryIds, true);
    }

    /**
     * The global factory filter chosen in the header (Frontend 4.2).
     * It narrows results; it can never widen them beyond accessible factories.
     */
    public function setFactoryScope(?string $factoryId): void
    {
        if ($factoryId !== null && ! $this->canAccessFactory($factoryId)) {
            $factoryId = null;
        }

        $this->factoryScopeId = $factoryId;
    }

    public function factoryScopeId(): ?string
    {
        return $this->factoryScopeId;
    }

    public function forget(): void
    {
        $this->companyId = null;
        $this->accessibleFactoryIds = [];
        $this->factoryScopeId = null;
        $this->resolved = false;
    }

    /**
     * Run something with no company resolved, and put the context back.
     *
     * For work that genuinely belongs to nobody's tenant — a notification
     * addressed to platform staff, who are members of no company. Without it,
     * BelongsToTenant fills company_id from whatever context happens to be set
     * when the row is written, and it cannot tell a caller who omitted the
     * field from one who deliberately meant null. That is how a platform
     * notification raised from inside a customer's page ends up stamped with
     * that customer's id, and then visible inside their system.
     *
     * Restores in a finally: leaving the context cleared after a failure would
     * make the next tenant-scoped query in the request throw, and the trace
     * would point at the query rather than at whatever went wrong here.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function runWithoutTenant(callable $callback): mixed
    {
        $companyId = $this->companyId;
        $factoryIds = $this->accessibleFactoryIds;
        $factoryScopeId = $this->factoryScopeId;
        $resolved = $this->resolved;

        $this->forget();

        try {
            return $callback();
        } finally {
            $this->companyId = $companyId;
            $this->accessibleFactoryIds = $factoryIds;
            $this->factoryScopeId = $factoryScopeId;
            $this->resolved = $resolved;
        }
    }
}
