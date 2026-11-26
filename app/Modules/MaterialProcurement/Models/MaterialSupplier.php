<?php

declare(strict_types=1);

namespace App\Modules\MaterialProcurement\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A supplier Material Procurement buys from (Supplier Master, AnnotechERP
 * 08/01): Local (Non-EPZ), Local (EPZ) or Foreign, with its owner, what it
 * supplies, and its VAT and TIN. The ERP's own list; Machinery keeps its
 * vendors apart.
 */
class MaterialSupplier extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['LOCAL_NON_EPZ', 'LOCAL_EPZ', 'FOREIGN'];

    protected $table = 'mp_suppliers';

    protected $fillable = [
        'company_id', 'code', 'name', 'short_name', 'supplier_type', 'owner_name', 'goods_service',
        'vat_reg_no', 'tin_no', 'address', 'active', 'created_by',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
