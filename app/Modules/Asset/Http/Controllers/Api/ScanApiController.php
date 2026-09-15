<?php

declare(strict_types=1);

namespace App\Modules\Asset\Http\Controllers\Api;

use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Services\ScanResolver;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * What a scanned QR code resolves to, over the wire (SRS 8, Data
 * Dictionary 5.2) — mirrors the former Blade `ScanController`, which this
 * replaces: the landing page itself now lives in Next.js
 * (`app/(app)/scan/[code]/page.js`, `.../scan/location/[code]/page.js`)
 * rather than a `layouts.mobile` view, now that the offline-capable
 * screen it hands off to (breakdown reporting) is itself in Next.js.
 *
 * The token is not a credential; a resolved scan still runs every
 * permission and policy check `ScanResolver::actionsFor()` does, and a
 * token belonging to another company simply does not resolve — a wrong or
 * foreign code is a plain 404, indistinguishable from a token that never
 * existed, so a scanned label can't be used to probe for foreign assets.
 *
 * `Gate::forUser($this->caller()->user)` throughout, never
 * `$this->authorize()`/ambient `Gate::allows()` — those resolve the
 * current user from the default session guard, which `AuthenticateApiToken`
 * never populates (it sets the caller only via `$request->setUserResolver`).
 * The same guard-agnostic-caller fix already applied to
 * `ManageCompanyUser::assertNotSelf()` earlier in this codebase's history.
 */
class ScanApiController extends ApiController
{
    public function __construct(private readonly ScanResolver $resolver) {}

    public function asset(string $code): JsonResponse
    {
        $asset = $this->resolver->asset($code);

        abort_if($asset === null, 404);

        $user = $this->caller()->user;

        if ($user === null || Gate::forUser($user)->denies('view', $asset)) {
            throw ApiException::of(ErrorCode::FORBIDDEN);
        }

        return ApiResponse::ok([
            'asset' => [
                'id' => $asset->id,
                'asset_code' => $asset->asset_code,
                'name' => $asset->name,
                'status' => $asset->status,
                'criticality' => $asset->criticality,
                'type' => $asset->type?->name,
                'factory' => $asset->factory?->name,
                'location' => $asset->location?->full_path ?? $asset->location?->name,
            ],
            'actions' => $this->resolver->actionsFor($asset, $user),
        ]);
    }

    public function location(string $code): JsonResponse
    {
        $location = $this->resolver->location($code);

        abort_if($location === null, 404);

        $user = $this->caller()->user;

        if ($user === null || Gate::forUser($user)->denies('viewAny', Asset::class)) {
            throw ApiException::of(ErrorCode::FORBIDDEN);
        }

        $assets = Asset::query()
            ->with('type:id,name')
            ->where('asset_location_id', $location->id)
            ->orderBy('asset_code')
            ->get();

        return ApiResponse::ok([
            'location' => [
                'id' => $location->id,
                'name' => $location->name,
                'full_path' => $location->full_path,
                'factory' => $location->factory?->name,
            ],
            'assets' => $assets->map(fn (Asset $asset): array => [
                'id' => $asset->id,
                'asset_code' => $asset->asset_code,
                'name' => $asset->name,
                'status' => $asset->status,
                'type' => $asset->type?->name,
            ])->all(),
        ]);
    }
}
