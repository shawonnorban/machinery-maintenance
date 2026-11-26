<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A top-level module of the menu tree (Access Control → Module Registration,
 * AnnotechERP 02/01). Platform-wide, not per company: the screens behind it
 * are the same code for everyone. Seeded from MenuRegistry.
 */
class MenuModule extends BaseModel
{
    protected $table = 'menu_modules';

    protected $fillable = ['code', 'name', 'name_bn', 'slug', 'icon', 'sort_order', 'section', 'description', 'is_system', 'active'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class);
    }
}
