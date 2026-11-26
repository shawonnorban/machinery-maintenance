<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** An accessories return challan to a supplier, against the receipt the goods came in on (Trims & Accessories, AnnotechERP 06/06). */
class AccReturn extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['REJECTED', 'EXCESS', 'SHORT_SHIPPED', 'OFF_SHADE', 'SAMPLE_RETURN', 'OTHER'];

    protected $table = 'merch_acc_returns';

    protected $fillable = [
        'company_id', 'challan_no', 'return_type', 'unit_company_id', 'supplier_id', 'mrr_id', 'challan_date', 'store_id', 'return_by', 'gate_pass_no', 'vehicle_no',
        'driver_name', 'remarks', 'status', 'finalized_at', 'finalized_by', 'created_by',
    ];

    protected function casts(): array
    {
        return ['challan_date' => 'date', 'finalized_at' => 'datetime'];
    }

    /** A finalised challan is locked: its goods have left stock. */
    public function isEditable(): bool
    {
        return $this->status === 'DRAFT';
    }
}
