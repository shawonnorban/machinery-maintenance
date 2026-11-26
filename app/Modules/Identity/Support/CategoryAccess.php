<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Modules\Identity\Models\RoleItemCategoryPermission;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\ItemMaster\Models\ItemCategory;
use App\Shared\Tenancy\TenantContext;
use InvalidArgumentException;

/**
 * Category Access Permission, as a question a screen can ask (AnnotechERP
 * 02/01): "which item categories may this person act in?".
 *
 * Null means unrestricted — none of the person's roles in this company has a
 * category row. Otherwise it is the union over their roles, and a granted
 * category carries its sub-categories with it (granting "Trims" grants
 * "Trims → Buttons"). A role with no rows of its own adds nothing to the
 * union but does not lift the others' restriction either: once any role
 * restricts, only granted categories count.
 */
class CategoryAccess
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @return list<string>|null
     */
    public function allowedCategoryIds(User $user, string $action): ?array
    {
        if (! in_array($action, RoleItemCategoryPermission::ACTIONS, true)) {
            throw new InvalidArgumentException("Unknown category action [{$action}].");
        }

        $roleIds = UserRole::query()
            ->where('company_id', $this->context->companyId())
            ->where('user_id', $user->id)
            ->pluck('role_id')
            ->unique();

        $rows = RoleItemCategoryPermission::query()->whereIn('role_id', $roleIds)->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $granted = $rows->where("can_{$action}", true)->pluck('item_category_id')->unique()->values()->all();

        return $this->withDescendants($granted);
    }

    public function allows(User $user, string $categoryId, string $action): bool
    {
        $allowed = $this->allowedCategoryIds($user, $action);

        return $allowed === null || in_array($categoryId, $allowed, true);
    }

    /**
     * @param  list<string>  $ids
     * @return list<string>
     */
    private function withDescendants(array $ids): array
    {
        $all = $ids;
        $frontier = $ids;

        // Three levels deep at most (ItemCategory::MAX_DEPTH).
        while ($frontier !== []) {
            $frontier = ItemCategory::query()->whereIn('parent_id', $frontier)->whereNotIn('id', $all)->pluck('id')->all();
            $all = array_merge($all, $frontier);
        }

        return array_values(array_unique($all));
    }
}
