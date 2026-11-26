<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A folder or a screen in the menu tree (Access Control → Menu Definition,
 * AnnotechERP 02/01). A leaf's V/C/E/D/A/P/X flags name permission strings
 * (MenuPermission); granting a flag to a role grants those permissions.
 */
class Menu extends BaseModel
{
    /** View, Create, Edit, Delete, Approve, Print, Export — Brickland's grant columns. */
    public const FLAGS = ['V', 'C', 'E', 'D', 'A', 'P', 'X'];

    public const SECURITY_LEVELS = ['PUBLIC', 'INTERNAL', 'RESTRICTED', 'CONFIDENTIAL'];

    protected $table = 'menus';

    protected $fillable = [
        'menu_module_id', 'parent_id', 'code', 'name', 'name_bn', 'description', 'url', 'security_level', 'icon',
        'sort_order', 'is_leaf', 'in_sidebar', 'open_new_window', 'is_report', 'is_system', 'active',
    ];

    protected function casts(): array
    {
        return [
            'is_leaf' => 'boolean', 'in_sidebar' => 'boolean', 'open_new_window' => 'boolean', 'is_report' => 'boolean',
            'is_system' => 'boolean', 'active' => 'boolean', 'sort_order' => 'integer',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(MenuModule::class, 'menu_module_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(MenuPermission::class);
    }
}
