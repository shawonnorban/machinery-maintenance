<?php

declare(strict_types=1);

namespace App\Modules\Api\Support;

use Illuminate\Support\Carbon;

/**
 * What `ApiCaller` needs from a bearer token, regardless of which of the two
 * schemes issued it.
 *
 * Two implementations exist during the migration to Sanctum (API 3, ADR
 * update): `LegacyTokenHandle` wraps the pre-Sanctum `ApiToken` model
 * (still the only scheme for machine/client-credential callers, which have
 * no Sanctum equivalent), and `SanctumTokenHandle` wraps
 * `Laravel\Sanctum\PersonalAccessToken` (person callers, going forward).
 * `AuthenticateApiToken` decides which one a bearer token resolves to;
 * everything downstream — `ApiCaller`, `AuthController` — reads only this
 * interface and does not know which scheme is behind it.
 */
interface TokenHandle
{
    public function id(): int|string;

    public function name(): string;

    public function companyId(): string;

    /** Null for a person's token. */
    public function apiClientId(): ?string;

    /** Null for a machine's token. */
    public function userId(): ?string;

    /**
     * The platform staff member acting through this token, if it was minted
     * by entering a support grant (Platform API §6). Null for an ordinary
     * login — this is the API equivalent of the web flow's
     * `session('impersonated_by')`, which `AuditRecorder` reads as the other
     * source of the same fact.
     */
    public function impersonatedBy(): ?string;

    public function expiresAt(): ?Carbon;

    public function isUsable(): bool;

    /**
     * Whether this token itself carries the permission — narrower than what
     * the account behind it could do, per ADR: a token is a subset, never a
     * superset, of its holder's authority.
     */
    public function permits(string $permission): bool;

    public function revoke(): void;

    public function touchUsage(): void;
}
