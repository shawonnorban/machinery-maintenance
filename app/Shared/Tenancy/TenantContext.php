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
}
