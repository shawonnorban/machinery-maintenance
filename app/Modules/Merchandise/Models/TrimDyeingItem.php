<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** One trim and shade being dyed on a dyeing programme (Accessories & Trims, AnnotechERP 06/08). */
class TrimDyeingItem extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_trim_dyeing_items';

    protected $fillable = ['company_id', 'program_id', 'item_id', 'description', 'colour_id', 'colour_name', 'qty', 'uom', 'sort_order'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'sort_order' => 'integer'];
    }
}
