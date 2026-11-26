<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** One material asked for: how much, what the store verified available and from which warehouse, and how much is issued. */
class MaterialRequisitionLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'proto_mr_lines';

    protected $fillable = ['company_id', 'requisition_id', 'item_id', 'material_group', 'fabric_type_id', 'color', 'size', 'description', 'specification', 'req_qty', 'unit', 'available_qty', 'warehouse_id', 'issued_qty', 'remarks', 'sort_order'];

    protected function casts(): array
    {
        return ['req_qty' => 'decimal:4', 'available_qty' => 'decimal:4', 'issued_qty' => 'decimal:4', 'sort_order' => 'integer'];
    }
}
