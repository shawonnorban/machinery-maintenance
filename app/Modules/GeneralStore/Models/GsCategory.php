<?php

declare(strict_types=1);

namespace App\Modules\GeneralStore\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * An item category — stationery, electrical, hardware, safety … (Item Category). General Store → Master Data (AnnotechERP 10/01).
 */
class GsCategory extends BaseModel
{
    use BelongsToTenant;

    /**
     * The one category list (the user's decision of 2026-11-15):
     * `item_categories`, the tree Access Control's category permission and
     * Machinery's spare parts read. A category added here is top level.
     */
    protected $table = 'item_categories';

    protected $fillable = ['company_id', 'parent_id', 'code', 'name', 'name_bn', 'description', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
