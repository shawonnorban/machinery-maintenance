<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Api;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Actions\AttemptLogin;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * How a platform administrator gets in (Platform API §1).
 *
 * Its own door, not `/auth/login`: that endpoint resolves a company for the
 * token it mints, and platform staff have none to resolve (SRS 5). Same
 * credential check either way — `AttemptLogin` is shared, so a rate-limit or
 * lockout rule tightened for one door is tightened for both.
 */
class PlatformAuthApiController extends ApiController
{
    public function login(Request $request, AttemptLogin $login, IssueApiToken $tokens): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $login->verify($data['email'], $data['password'], (string) $request->ip());

        if (! $user->is_platform_admin) {
            // The same generic failure as a wrong password (API 3):
            // confirming an address belongs to a non-admin account is
            // exactly the enumeration a login endpoint must not offer.
            throw ApiException::of(ErrorCode::UNAUTHENTICATED);
        }

        ['token' => $token, 'plain' => $plain] = $tokens->forPlatformAdmin(
            $user,
            $data['device_name'] ?? __('api.default_token_name'),
        );

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return ApiResponse::created([
            'access_token' => $plain,
            'token_type' => 'Bearer',
            'expires_at' => $token->expires_at?->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::ok([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'locale' => $user->locale,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = PersonalAccessToken::findToken((string) $request->bearerToken());
        $token?->delete();

        return ApiResponse::noContent();
    }
}
