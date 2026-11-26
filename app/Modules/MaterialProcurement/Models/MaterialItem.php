<?php

declare(strict_types=1);

namespace App\Modules\MaterialProcurement\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A fabric or a trim & accessory a requisition may ask for (Material
 * Procurement → Fabric Master, Trims & Accessories Master, AnnotechERP 08/01).
 * Only an active item is offered on a new requisition.
 */
class MaterialItem extends BaseModel
{
    use BelongsToTenant;

    public const KINDS = ['FABRIC', 'TRIMS'];

    protected $table = 'mp_items';

    protected $fillable = [
        'company_id', 'kind', 'item_code', 'name', 'category', 'sub_category', 'gsm', 'width', 'composition',
        'size', 'colour', 'uom', 'specification', 'description', 'remarks', 'active',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
