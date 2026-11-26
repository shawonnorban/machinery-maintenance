<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\ItemMaster\Models\ItemCategory;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Access Control → Category Access Permission (AnnotechERP 02/01): which item
 * categories a role may view, create, edit or approve in. A role with no rows
 * is not restricted by category at all; once it has any, only those count.
 */
class RoleItemCategoryPermission extends BaseModel
{
    use BelongsToTenant;

    public const ACTIONS = ['view', 'create', 'edit', 'approve'];

    protected $table = 'role_item_category_permissions';

    protected $fillable = ['company_id', 'role_id', 'item_category_id', 'can_view', 'can_create', 'can_edit', 'can_approve'];

    protected function casts(): array
    {
        return ['role_id' => 'integer', 'can_view' => 'boolean', 'can_create' => 'boolean', 'can_edit' => 'boolean', 'can_approve' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id');
    }
}
