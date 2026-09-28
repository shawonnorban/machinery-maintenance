<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;

/**
 * Platform staff for API tests: a user with no company membership and
 * `is_platform_admin = true`, plus a bearer token minted the same way
 * `PlatformAuthApiController::login` does.
 */
class PlatformFixture
{
    public static function staff(string $email = 'support@platform.test', string $name = 'Platform Support'): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => 'correct-horse-battery',
            'status' => 'ACTIVE',
            'locale' => 'en',
            'is_platform_admin' => true,
        ]);
    }

    public static function token(User $staff): string
    {
        return app(IssueApiToken::class)->forPlatformAdmin($staff, 'Test suite')['plain'];
    }
}
