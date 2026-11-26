<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** An item type the Garment Items step offers under its item group — T-Shirt, Polo Shirt, Trouser … (Master Data, AnnotechERP 06/01). */
class ItemType extends BaseModel
{
    use BelongsToTenant;

    public const GROUPS = ['KNIT_TOP', 'KNIT_BOTTOM', 'WOVEN_TOP', 'WOVEN_BOTTOM'];

    protected $table = 'item_types';

    protected $fillable = ['company_id', 'item_group', 'code', 'name', 'remarks', 'sort_order', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'sort_order' => 'integer'];
    }
}
