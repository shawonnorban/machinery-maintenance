<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A certificate issued for a shipment, typed from the Certificate Master, with its expiry, issuing authority and attachment. Sales & Shipment → Export Documentation (AnnotechERP 21/06).
 */
class SsCertificate extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['ACTIVE', 'CANCELLED'];

    protected $table = 'ss_certificates';

    protected $fillable = ['company_id', 'shipment_id', 'certificate_type_id', 'certificate_name', 'certificate_no', 'issue_date', 'expiry_date', 'issuing_authority', 'status', 'file_id', 'file_name', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['issue_date' => 'date', 'expiry_date' => 'date'];
    }
}
