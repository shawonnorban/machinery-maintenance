<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A sub-contract receipt (SCR-…): what came back against a delivered chalan — finished goods, left-over or rejection; finalized (locked) or cancelled. Sales & Shipment → Sub-Contract (AnnotechERP 21/03).
 */
class SsScReceipt extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['FINISHED_GOODS', 'LEFT_OVER', 'REJECTION'];

    protected $table = 'ss_sc_receipts';

    protected $fillable = ['company_id', 'mrr_no', 'delivery_id', 'mrr_date', 'store_id', 'customer_id', 'receive_type', 'remarks', 'is_revised', 'state', 'finalized', 'finalized_at', 'finalized_by', 'cancelled_at', 'cancelled_by', 'created_by'];

    protected function casts(): array
    {
        return ['mrr_date' => 'date', 'is_revised' => 'boolean', 'finalized' => 'boolean', 'finalized_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }
}
