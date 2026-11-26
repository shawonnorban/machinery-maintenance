<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A garment item in its group — Knit Tops: Basic T-Shirt, Polo Shirt … Work Study → Master Data (AnnotechERP 14/01).
 */
class WsGarmentItem extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ws_garment_items';

    protected $fillable = ['company_id', 'item_group', 'name', 'code', 'sl', 'active'];

    protected function casts(): array
    {
        return ['sl' => 'integer', 'active' => 'boolean'];
    }
}
