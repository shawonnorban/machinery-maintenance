<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * An accessory a Pre-Budget BOM line picks: its code, name, category and the
 * unit (UOM master code) it is usually bought in. Pre-Budget → Master Data.
 */
class PbAccessoryItem extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'pb_accessory_items';

    protected $fillable = ['company_id', 'code', 'name', 'category', 'uom', 'description', 'active', 'created_by'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
