<?php

declare(strict_types=1);

namespace App\Modules\Api\Support;

use App\Modules\Api\Models\ApiToken;
use Illuminate\Support\Carbon;

/**
 * Wraps the pre-Sanctum `ApiToken` model. Still the only scheme for
 * machine/client-credential callers (API 4.2) — Sanctum has no equivalent
 * for a caller with no user behind it — and, during the migration window,
 * for any person's token minted before the cutover.
 */
class LegacyTokenHandle implements TokenHandle
{
    public function __construct(private readonly ApiToken $token) {}

    public function id(): string
    {
        return $this->token->id;
    }

    public function name(): string
    {
        return $this->token->name;
    }

    public function companyId(): string
    {
        return (string) $this->token->company_id;
    }

    public function apiClientId(): ?string
    {
        return $this->token->api_client_id;
    }

    public function userId(): ?string
    {
        return $this->token->user_id;
    }

    /** The legacy scheme predates support-access impersonation entirely. */
    public function impersonatedBy(): ?string
    {
        return null;
    }

    public function expiresAt(): ?Carbon
    {
        return $this->token->expires_at;
    }

    public function isUsable(): bool
    {
        return $this->token->isUsable();
    }

    public function permits(string $permission): bool
    {
        return $this->token->permits($permission);
    }

    public function revoke(): void
    {
        $this->token->revoke();
    }

    public function touchUsage(): void
    {
        $this->token->touchUsage();
    }
}
