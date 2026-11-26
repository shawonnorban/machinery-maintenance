<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A fabric construction — single jersey, rib, fleece … — and the parts it may be cut for (Fabric Type Master). Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class FabricType extends BaseModel
{
    use BelongsToTenant;

    public const CATEGORIES = ['KNIT', 'SWEATER', 'WOVEN'];

    public const CONSTRUCTIONS = ['CIRCULAR', 'FLAT'];

    protected $table = 'fabric_types';

    protected $fillable = ['company_id', 'code', 'name', 'short_name', 'fabric_group', 'category', 'construction', 'is_base', 'parent_id', 'is_rib', 'sort_order', 'active'];

    protected function casts(): array
    {
        return ['is_base' => 'boolean', 'is_rib' => 'boolean', 'sort_order' => 'integer', 'active' => 'boolean'];
    }
}
