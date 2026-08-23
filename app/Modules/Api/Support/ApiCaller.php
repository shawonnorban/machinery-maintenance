<?php

declare(strict_types=1);

namespace App\Modules\Api\Support;

use App\Modules\Api\Models\ApiClient;
use App\Modules\Api\Models\ApiToken;
use App\Modules\Identity\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;

/**
 * Who is on the other end of an API request, and what they may do.
 *
 * Two kinds of caller reach the same endpoints and they are not the same
 * thing. A person carries the permissions of their roles, which change when
 * somebody edits a role. A machine carries an explicit list of permission
 * codes fixed when its credentials were minted, and nothing else — an ERP
 * given a credential to post meter readings must not gain the ability to close
 * work orders because somebody widened a role it was never part of.
 *
 * Both are then narrowed again by the token's own abilities, so a person can
 * mint a read-only token for a dashboard without handing it their whole
 * account.
 *
 * The single `can()` here is why API controllers do not have to know which
 * kind of caller they are serving.
 */
class ApiCaller
{
    private function __construct(
        public readonly ApiToken $token,
        public readonly string $companyId,
        public readonly ?User $user,
        public readonly ?ApiClient $client,
    ) {}

    public static function forUser(ApiToken $token, User $user): self
    {
        return new self($token, (string) $token->company_id, $user, null);
    }

    public static function forClient(ApiToken $token, ApiClient $client): self
    {
        return new self($token, (string) $token->company_id, null, $client);
    }

    public function isMachine(): bool
    {
        return $this->client !== null;
    }

    /**
     * Every check goes through both gates.
     *
     * The token's abilities first because they are the cheaper and narrower
     * test, then whatever authority stands behind it.
     */
    public function can(string $permission): bool
    {
        if (! $this->token->permits($permission)) {
            return false;
        }

        if ($this->client !== null) {
            return $this->client->allows($permission);
        }

        // Gate::forUser rather than Gate::allows: nothing has logged this user
        // in, and nothing should — a bearer token is not a session.
        return $this->user !== null && Gate::forUser($this->user)->allows($permission);
    }

    /**
     * The factories this caller may read and write.
     *
     * A machine client is company-wide: it has no role assignment to narrow it
     * and inventing one would be guessing. A person is narrowed exactly as the
     * web application narrows them.
     *
     * @return list<string>
     */
    public function accessibleFactoryIds(TenantContext $context): array
    {
        return $context->accessibleFactoryIds();
    }

    /**
     * What this caller can do, as a flat list.
     *
     * Served by `GET /auth/permissions` so a client can hide what it would be
     * refused rather than discovering it one 403 at a time.
     *
     * @param  list<string>  $allPermissions  every code the platform defines
     * @return list<string>
     */
    public function permissionCodes(array $allPermissions): array
    {
        return array_values(array_filter(
            $allPermissions,
            fn (string $code): bool => $this->can($code),
        ));
    }

    public function auditUserId(): ?string
    {
        return $this->user?->id;
    }

    public function label(): string
    {
        return $this->user?->name ?? ($this->client?->name ?? 'unknown');
    }
}
