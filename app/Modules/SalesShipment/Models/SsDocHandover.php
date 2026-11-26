<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A commercial document handover (DH-…) from Commercial to Shipping: invoice, packing list, EXP and certificate; pending, handed over, received or returned. Sales & Shipment → Shipment (AnnotechERP 21/05).
 */
class SsDocHandover extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['PENDING', 'HANDED_OVER', 'RECEIVED', 'RETURNED'];

    protected $table = 'ss_doc_handovers';

    protected $fillable = ['company_id', 'handover_no', 'kind', 'handover_date', 'shipment_id', 'invoice_id', 'packing_list_id', 'exp_no', 'certificate', 'document_type', 'document_list', 'submitted_by', 'received_by', 'status', 'file_id', 'file_name', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['handover_date' => 'date'];
    }
}
