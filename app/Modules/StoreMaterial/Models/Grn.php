<?php

declare(strict_types=1);

namespace App\Modules\StoreMaterial\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * In-House / GRN: a receive against an approved PR or direct, confirmed at once and reversed — never deleted — by the Store Manager. Store & Material (AnnotechERP 09).
 */
class Grn extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'sm_grns';

    protected $fillable = ['company_id', 'grn_no', 'grn_date', 'kind', 'requisition_id', 'supplier_id', 'supplier_name', 'buyer', 'po_number', 'style', 'order_no', 'currency', 'received_by', 'remarks', 'status', 'confirmed_by', 'reversed_by', 'reversed_at', 'reverse_reason'];

    protected function casts(): array
    {
        return ['grn_date' => 'date', 'reversed_at' => 'datetime'];
    }
}
