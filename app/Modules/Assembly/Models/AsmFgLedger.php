<?php

declare(strict_types=1);

namespace App\Modules\Assembly\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One movement of finished goods by PO, colour and size: in from Packing Out, out by Shipment, back in on a Shipment Reversal. Assembly Floor → Ready For Shipment (AnnotechERP 20/06).
 */
class AsmFgLedger extends BaseModel
{
    use BelongsToTenant;

    public const DOC_TYPES = ['PACKING_OUT', 'SHIPMENT', 'SHIPMENT_REVERSAL'];

    protected $table = 'asm_fg_ledger';

    protected $fillable = ['company_id', 'order_id', 'po_no', 'buyer_id', 'style', 'colour', 'size', 'batch_id', 'doc_type', 'doc_no', 'doc_id', 'qty_in', 'qty_out', 'posted_at', 'created_by'];

    protected function casts(): array
    {
        return ['qty_in' => 'integer', 'qty_out' => 'integer', 'posted_at' => 'datetime'];
    }
}
