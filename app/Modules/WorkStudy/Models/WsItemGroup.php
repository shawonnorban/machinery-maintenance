<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A garment item group (Knit Tops GRP-KNIT …); its categories name it. Work Study → Item Specification (AnnotechERP 14/04).
 */
class WsItemGroup extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ws_item_groups';

    protected $fillable = ['company_id', 'name', 'code', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }
}
