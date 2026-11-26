<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One company's own name, order, icon or visibility for a shared module or
 * menu (AnnotechERP 02/01). A null column follows the code's value.
 */
class MenuOverride extends BaseModel
{
    use BelongsToTenant;

    /** The columns a company may set over a module or menu. */
    public const FIELDS = ['name', 'name_bn', 'description', 'icon', 'sort_order', 'security_level', 'open_new_window', 'active'];

    protected $table = 'menu_overrides';

    protected $fillable = ['company_id', 'menu_module_id', 'menu_id', 'name', 'name_bn', 'description', 'icon', 'sort_order', 'security_level', 'open_new_window', 'active'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'open_new_window' => 'boolean', 'active' => 'boolean'];
    }
}
