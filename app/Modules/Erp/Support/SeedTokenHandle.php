<?php

declare(strict_types=1);

namespace App\Modules\Erp\Support;

use App\Modules\Api\Support\TokenHandle;
use Illuminate\Support\Carbon;

/**
 * The caller a master-data seed runs as: the company's owner, held in memory
 * only — never stored, never usable over HTTP — so "raised by" and "prepared
 * by" read the way they would had the owner keyed the rows in.
 */
final class SeedTokenHandle implements TokenHandle
{
    public function __construct(private readonly string $companyId, private readonly string $userId) {}

    public function id(): int|string
    {
        return 'seed';
    }

    public function name(): string
    {
        return 'Master data seed';
    }

    public function companyId(): string
    {
        return $this->companyId;
    }

    public function apiClientId(): ?string
    {
        return null;
    }

    public function userId(): ?string
    {
        return $this->userId;
    }

    public function impersonatedBy(): ?string
    {
        return null;
    }

    public function pinSessionId(): ?string
    {
        return null;
    }

    public function expiresAt(): ?Carbon
    {
        return null;
    }

    public function isUsable(): bool
    {
        return false;
    }

    public function permits(string $permission): bool
    {
        return true;
    }

    public function revoke(): void {}

    public function touchUsage(): void {}
}
