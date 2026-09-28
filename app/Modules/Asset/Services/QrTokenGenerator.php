<?php

declare(strict_types=1);

namespace App\Modules\Asset\Services;

use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetLocation;
use App\Shared\Scopes\TenantScope;
use RuntimeException;

/**
 * Generates the opaque token printed on an asset or location QR label
 * (Data Dictionary 5.1).
 *
 * The token is deliberately not derived from the id and not sequential. A
 * printed label on a factory floor must reveal no asset id, no company id and
 * no count of assets; a sequential code would let anyone photograph one label
 * and enumerate the whole fleet.
 *
 * The token is not a credential. Resolving it still requires an authenticated
 * session with permission on the asset.
 */
class QrTokenGenerator
{
    /**
     * Crockford-style alphabet without I, L, O and U: those are the characters
     * a storekeeper misreads off an oily label, and the ones that form
     * unintended words.
     */
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const LENGTH = 12;

    private const MAX_ATTEMPTS = 10;

    public function forAsset(string $companyId): string
    {
        return $this->unique(
            fn (string $token) => Asset::withoutGlobalScope(TenantScope::class)
                ->where('company_id', $companyId)
                ->where('qr_code', $token)
                ->withTrashed()
                ->exists(),
        );
    }

    public function forLocation(string $companyId): string
    {
        return $this->unique(
            fn (string $token) => AssetLocation::withoutGlobalScope(TenantScope::class)
                ->where('company_id', $companyId)
                ->where('qr_code', $token)
                ->exists(),
        );
    }

    /**
     * @param  callable(string): bool  $taken
     */
    private function unique(callable $taken): string
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $token = $this->token();

            if (! $taken($token)) {
                return $token;
            }
        }

        // 32^12 is a large space; exhausting ten attempts means something is
        // wrong with the generator, not that we were unlucky.
        throw new RuntimeException('Could not generate a unique QR token after '.self::MAX_ATTEMPTS.' attempts.');
    }

    private function token(): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $token = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $token .= self::ALPHABET[random_int(0, $max)];
        }

        return $token;
    }
}
