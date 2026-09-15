<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api;

use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;

/**
 * The full permission catalog, grouped by module (API 4.1).
 *
 * What a role can be built from — every code that exists, not what the
 * caller themselves holds; `GET /auth/permissions` (API 3) answers that
 * question instead. Gated the same as roles: permissions are what a role is
 * made of, so the catalog is visible to whoever may manage one.
 */
class PermissionApiController extends ApiController
{
    public function index(): JsonResponse
    {
        $this->allow('admin.role.manage');

        $grouped = Permission::query()
            ->orderBy('name')
            ->get(['name', 'description', 'module', 'is_elevated'])
            ->groupBy('module')
            ->map(fn ($permissions) => $permissions->map(fn (Permission $p): array => [
                'code' => $p->name,
                'name' => $p->description,
                'is_elevated' => $p->is_elevated,
            ])->values())
            ->all();

        return ApiResponse::ok($grouped);
    }
}
