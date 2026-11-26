<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A scan or file kept against a shipment, by document type and uploader. Sales & Shipment → Export Documentation (AnnotechERP 21/06).
 */
class SsShipmentAttachment extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['Commercial Invoice', 'Packing List', 'EXP', 'B/L', 'AWB', 'Certificate', 'Inspection Report', 'Insurance', 'Photo', 'Other'];

    protected $table = 'ss_shipment_attachments';

    protected $fillable = ['company_id', 'shipment_id', 'document_type', 'title', 'file_id', 'file_name', 'remarks', 'uploaded_by', 'uploaded_at'];

    protected function casts(): array
    {
        return ['uploaded_at' => 'datetime'];
    }
}
