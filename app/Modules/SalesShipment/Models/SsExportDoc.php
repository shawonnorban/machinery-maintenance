<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * An export document filed on a shipment — EXP, inspection, insurance, COO, GSP / REX, B/L or AWB — with its dates, issuing authority, status and attachment. Sales & Shipment → Export Documentation (AnnotechERP 21/06).
 */
class SsExportDoc extends BaseModel
{
    use BelongsToTenant;

    /** The document types each screen files, by family. */
    public const FAMILIES = [
        'EXPORT' => ['EXP', 'INSPECTION', 'INSURANCE', 'OTHER'],
        'COO' => ['COO'],
        'GSP' => ['GSP', 'REX'],
        'BLAWB' => ['BILL_OF_LADING', 'AIR_WAYBILL'],
    ];

    public const STATUSES = ['PENDING', 'RECEIVED', 'SUBMITTED', 'APPROVED', 'CANCELLED'];

    protected $table = 'ss_export_docs';

    protected $fillable = ['company_id', 'shipment_id', 'family', 'doc_type', 'document_no', 'document_date', 'issue_date', 'expiry_date', 'issuing_authority', 'status', 'file_id', 'file_name', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['document_date' => 'date', 'issue_date' => 'date', 'expiry_date' => 'date'];
    }
}
