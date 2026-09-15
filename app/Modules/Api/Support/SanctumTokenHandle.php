<?php

declare(strict_types=1);

namespace App\Modules\Api\Support;

use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Wraps a Sanctum `PersonalAccessToken` — the scheme person callers are
 * issued on going forward (API 3). `company_id` is a column this
 * application added to Sanctum's table: a token is minted for exactly one
 * company, the same invariant the legacy `ApiToken` table enforced, so
 * tenant context still comes from the token alone rather than a
 * client-supplied header.
 */
class SanctumTokenHandle implements TokenHandle
{
    public function __construct(private readonly PersonalAccessToken $token) {}

    public function id(): int
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

    /** Always null: Sanctum tokens are issued only to person callers. */
    public function apiClientId(): ?string
    {
        return null;
    }

    public function userId(): ?string
    {
        return (string) $this->token->tokenable_id;
    }

    public function impersonatedBy(): ?string
    {
        return $this->token->impersonated_by;
    }

    public function expiresAt(): ?Carbon
    {
        return $this->token->expires_at;
    }

    /**
     * A row that was found and not expired. Sanctum has no revoked_at:
     * revoking a token deletes the row, so "found" already means "not
     * revoked".
     */
    public function isUsable(): bool
    {
        return $this->token->expires_at === null || $this->token->expires_at->isFuture();
    }

    public function permits(string $permission): bool
    {
        return $this->token->can($permission);
    }

    public function revoke(): void
    {
        $this->token->delete();
    }

    public function touchUsage(): void
    {
        $this->token->forceFill(['last_used_at' => Carbon::now()])->save();
    }
}
