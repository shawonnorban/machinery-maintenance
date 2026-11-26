<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A fabric return challan to a supplier, against the fabric MRR the batches came in on (Trims & Accessories, AnnotechERP 06/06). */
class FabricReturn extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_pfab_returns';

    protected $fillable = [
        'company_id', 'challan_no', 'return_type', 'unit_company_id', 'supplier_id', 'mrr_id', 'challan_date', 'store_id', 'return_by', 'gate_pass_no', 'vehicle_no',
        'driver_name', 'remarks', 'status', 'finalized_at', 'finalized_by', 'created_by',
    ];

    protected function casts(): array
    {
        return ['challan_date' => 'date', 'finalized_at' => 'datetime'];
    }

    /** A finalised challan is locked: its batches have left stock. */
    public function isEditable(): bool
    {
        return $this->status === 'DRAFT';
    }
}
