<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Controllers\Api;

use App\Modules\Api\Actions\ManageApiClient;
use App\Modules\Api\Models\ApiClient;
use App\Modules\Api\Models\ApiToken;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

/**
 * Where a person mints a machine's credentials, over the wire (API 4.2;
 * mirrors the web `ApiClientController`, which this delegates to unchanged
 * per ADR-003 — `ManageApiClient` carries every create/rotate/revoke rule).
 *
 * The one thing that cannot carry over unchanged: the web shows a freshly
 * minted secret exactly once via a flashed session value, which a stateless
 * JSON API has no equivalent of. Here the secret is simply returned in the
 * `store`/`rotate` response body itself and nowhere else — the caller is
 * responsible for showing it to whoever asked and then discarding it, same
 * as the web screen's own "never again" rule, just carried by the response
 * rather than a second request.
 */
class ApiClientApiController extends ApiController
{
    public function __construct(private readonly ManageApiClient $clients) {}

    public function index(): JsonResponse
    {
        $this->allow('admin.api_client.manage');

        $clients = ApiClient::query()
            ->with('creator:id,name')
            ->withCount(['tokens as active_tokens' => fn ($q) => $q->whereNull('revoked_at')])
            ->orderBy('name')
            ->get();

        return ApiResponse::ok($clients->map(fn (ApiClient $c): array => $this->summary($c))->all());
    }

    /**
     * Every permission code the platform defines, grouped by module —
     * mirrors the web index's own `Permission::query()->groupBy('module')`.
     * The scope list is shown, not counted: "8 scopes" tells nobody whether
     * this credential can close a work order.
     */
    public function formOptions(): JsonResponse
    {
        $this->allow('admin.api_client.manage');

        $grouped = Permission::query()
            ->orderBy('name')
            ->get(['name', 'description', 'module'])
            ->groupBy('module')
            ->map(fn ($group, $module) => [
                'module' => $module,
                'permissions' => $group->map(fn (Permission $p): array => [
                    'name' => $p->name,
                    'description' => $p->description,
                ])->values()->all(),
            ])
            ->values();

        return ApiResponse::ok(['modules' => $grouped->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('admin.api_client.manage');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['string', 'max:128'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        try {
            ['client' => $client, 'secret' => $secret] = $this->clients->create(
                $data['name'],
                array_values($data['scopes']),
                $data['expires_at'] ?? null,
                (string) $this->caller()->auditUserId(),
            );
        } catch (ValidationException $e) {
            throw ApiException::of(ErrorCode::VALIDATION_ERROR, implode(' ', $e->validator->errors()->all()), $e->errors());
        }

        return ApiResponse::created($this->summary($client) + ['secret' => $secret]);
    }

    public function update(Request $request, ApiClient $client): JsonResponse
    {
        $this->allow('admin.api_client.manage');

        $data = $request->validate([
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['string', 'max:128'],
        ]);

        try {
            $updated = $this->clients->updateScopes($client, array_values($data['scopes']));
        } catch (ValidationException $e) {
            throw ApiException::of(ErrorCode::VALIDATION_ERROR, implode(' ', $e->validator->errors()->all()), $e->errors());
        }

        return ApiResponse::ok($this->summary($updated));
    }

    public function rotate(ApiClient $client): JsonResponse
    {
        $this->allow('admin.api_client.manage');

        ['client' => $updated, 'secret' => $secret] = $this->clients->rotateSecret($client);

        return ApiResponse::ok($this->summary($updated) + ['secret' => $secret]);
    }

    public function revoke(ApiClient $client): JsonResponse
    {
        $this->allow('admin.api_client.manage');

        $this->clients->revoke($client);

        return ApiResponse::noContent();
    }

    /**
     * A token a person holds, given up from a screen as well as from an
     * endpoint — somebody who has lost a tablet cannot call `/auth/logout`
     * from it.
     */
    public function revokeToken(ApiToken $token): JsonResponse
    {
        $this->allow('admin.api_client.manage');

        $token->revoke();

        return ApiResponse::noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(ApiClient $client): array
    {
        return [
            'id' => $client->id,
            'name' => $client->name,
            'client_id' => $client->client_id,
            'scopes' => $client->scopes(),
            'status' => $client->status,
            'is_usable' => $client->isUsable(),
            'active_tokens' => $client->active_tokens ?? null,
            'creator' => $client->relationLoaded('creator') && $client->creator !== null
                ? ['id' => $client->creator->id, 'name' => $client->creator->name]
                : null,
            'last_used_at' => $client->last_used_at?->toIso8601String(),
            'expires_at' => $client->expires_at?->toIso8601String(),
            'secret_rotated_at' => $client->secret_rotated_at?->toIso8601String(),
            'revoked_at' => $client->revoked_at?->toIso8601String(),
            'created_at' => $client->created_at?->toIso8601String(),
        ];
    }
}
