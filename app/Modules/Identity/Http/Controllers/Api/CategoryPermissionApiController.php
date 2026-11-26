<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RoleItemCategoryPermission;
use App\Modules\ItemMaster\Models\ItemCategory;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Access Control → Category Access Permission (AnnotechERP 02/01): per role,
 * which item categories it may view, create, edit and approve in. Rows belong
 * to this company, so a company may restrict a shared system role too.
 */
class CategoryPermissionApiController extends ApiController
{
    public function __construct(private readonly TenantContext $context) {}

    /** The company's category tree, each row with this role's four ticks. */
    public function show(Role $role): JsonResponse
    {
        $this->allow('admin.role.manage');
        $this->assertVisible($role);

        $granted = RoleItemCategoryPermission::query()->where('role_id', $role->id)->get()->keyBy('item_category_id');

        return ApiResponse::ok([
            'role' => ['id' => $role->id, 'code' => $role->name, 'name' => $role->description],
            'restricted' => $granted->isNotEmpty(),
            'categories' => $this->ordered(ItemCategory::query()->orderBy('name')->get())->map(function (array $entry) use ($granted): array {
                /** @var ItemCategory $category */
                $category = $entry['category'];
                $row = $granted->get($category->id);

                return [
                    'id' => $category->id,
                    'code' => $category->code,
                    'name' => $category->name,
                    'name_bn' => $category->name_bn,
                    'depth' => $entry['depth'],
                    'parent_id' => $category->parent_id,
                    'can_view' => (bool) $row?->can_view,
                    'can_create' => (bool) $row?->can_create,
                    'can_edit' => (bool) $row?->can_edit,
                    'can_approve' => (bool) $row?->can_approve,
                ];
            })->values()->all(),
        ]);
    }

    /** Category Access Permission's Records: every role's category rows in this company. */
    public function index(): JsonResponse
    {
        $this->allow('admin.role.manage');

        $rows = RoleItemCategoryPermission::query()->with('category:id,name,code')->get();
        $roles = Role::query()->whereIn('id', $rows->pluck('role_id')->unique())->get()->keyBy('id');

        return ApiResponse::ok($rows->map(fn (RoleItemCategoryPermission $row): array => [
            'id' => $row->id,
            'role_id' => $row->role_id,
            'role' => $roles->get($row->role_id)?->description ?? $roles->get($row->role_id)?->name,
            'item_category_id' => $row->item_category_id,
            'category' => $row->category?->name,
            'category_code' => $row->category?->code,
            'can_view' => $row->can_view,
            'can_create' => $row->can_create,
            'can_edit' => $row->can_edit,
            'can_approve' => $row->can_approve,
        ])->sortBy([['role', 'asc'], ['category', 'asc']])->values()->all());
    }

    /** "New category permission": one category's ticks for one role; all clear removes the row. */
    public function saveOne(Request $request, Role $role, ItemCategory $category): JsonResponse
    {
        $this->allow('admin.role.manage');
        $this->assertVisible($role);

        $data = $request->validate([
            'can_view' => ['sometimes', 'boolean'],
            'can_create' => ['sometimes', 'boolean'],
            'can_edit' => ['sometimes', 'boolean'],
            'can_approve' => ['sometimes', 'boolean'],
        ]);
        $ticks = [];
        foreach (RoleItemCategoryPermission::ACTIONS as $action) {
            $ticks["can_{$action}"] = (bool) ($data["can_{$action}"] ?? false);
        }

        if (! in_array(true, $ticks, true)) {
            RoleItemCategoryPermission::query()->where('role_id', $role->id)->where('item_category_id', $category->id)->delete();
        } else {
            // Doing anything in a category means seeing it.
            $ticks['can_view'] = true;
            RoleItemCategoryPermission::query()->updateOrCreate(['role_id' => $role->id, 'item_category_id' => $category->id], $ticks);
        }

        return $this->show($role);
    }

    /** Replaces the role's rows; a category with no tick is no row at all. */
    public function save(Request $request, Role $role): JsonResponse
    {
        $this->allow('admin.role.manage');
        $this->assertVisible($role);

        $data = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*.item_category_id' => ['required', 'string', 'size:26', Rule::exists('item_categories', 'id')->where('company_id', $this->context->companyId())],
            'permissions.*.can_view' => ['sometimes', 'boolean'],
            'permissions.*.can_create' => ['sometimes', 'boolean'],
            'permissions.*.can_edit' => ['sometimes', 'boolean'],
            'permissions.*.can_approve' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($role, $data): void {
            RoleItemCategoryPermission::query()->where('role_id', $role->id)->delete();

            foreach ($data['permissions'] as $entry) {
                $ticks = [];
                foreach (RoleItemCategoryPermission::ACTIONS as $action) {
                    $ticks["can_{$action}"] = (bool) ($entry["can_{$action}"] ?? false);
                }
                if (! in_array(true, $ticks, true)) {
                    continue;
                }
                // Doing anything in a category means seeing it.
                $ticks['can_view'] = true;

                RoleItemCategoryPermission::create(['role_id' => $role->id, 'item_category_id' => $entry['item_category_id']] + $ticks);
            }
        });

        return $this->show($role);
    }

    /**
     * Depth-first, so each sub-category sits under its parent.
     *
     * @param  Collection<int, ItemCategory>  $categories
     * @return Collection<int, array{category: ItemCategory, depth: int}>
     */
    private function ordered(Collection $categories): Collection
    {
        $children = $categories->groupBy(fn (ItemCategory $c) => $c->parent_id ?? 'root');
        $out = collect();
        $walk = function (string $parent, int $depth) use (&$walk, $children, $out): void {
            foreach ($children->get($parent, collect()) as $category) {
                $out->push(['category' => $category, 'depth' => $depth]);
                $walk($category->id, $depth + 1);
            }
        };
        $walk('root', 1);

        return $out;
    }

    /** Another company's role is not reachable (404), the same as ManageRole. */
    private function assertVisible(Role $role): void
    {
        if ($role->company_id !== null && $role->company_id !== $this->context->companyId()) {
            abort(404);
        }
    }
}
