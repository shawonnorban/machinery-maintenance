<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A dyed batch received on the finishing floor from the Dye House, roll and weight checked against the challan — the batch's finishing card, which carries its WIP stage. Finishing Line → DF Production (AnnotechERP 18/02).
 */
class FinBatchReceipt extends BaseModel
{
    use BelongsToTenant;

    public const DIAS = ['OPEN', 'TUBE', 'BOTH'];

    public const BOOKINGS = ['IN_HOUSE', 'SUB_CONTRACT', 'SAMPLE'];

    protected $table = 'fin_batch_receipts';

    protected $fillable = ['company_id', 'receipt_no', 'receive_date', 'batch_id', 'dia_type', 'booking_type', 'challan_no', 'challan_qty_kg', 'challan_rolls', 'recv_qty_kg', 'recv_rolls', 'floor_id', 'stage', 'stage_at', 'last_load_at', 'last_machine_id', 'last_process_id', 'status', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['receive_date' => 'date', 'challan_qty_kg' => 'decimal:3', 'challan_rolls' => 'integer', 'recv_qty_kg' => 'decimal:3', 'recv_rolls' => 'integer', 'stage_at' => 'datetime', 'last_load_at' => 'datetime'];
    }
}
