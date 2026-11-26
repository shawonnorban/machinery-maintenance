<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A component a garment item is made of, in printed order (Item & Process Grouping). Work Study → Master Data (AnnotechERP 14/01).
 */
class WsItemPart extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ws_item_parts';

    protected $fillable = ['company_id', 'garment_item_id', 'garment_part_id', 'component_sl', 'included'];

    protected function casts(): array
    {
        return ['component_sl' => 'integer', 'included' => 'boolean'];
    }
}
