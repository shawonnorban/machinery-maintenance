<?php

declare(strict_types=1);

namespace App\Modules\ColourLab\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A certificate the lab holds: its type, the equipment it covers, the filed document, when it was applied for, issued and runs out, and who hears before it does. Colour Lab → Operations (AnnotechERP 16/01).
 */
class LabCertificate extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'lab_certificates';

    protected $fillable = ['company_id', 'cert_type_id', 'equipment_id', 'document_ref', 'upload_doc', 'company_document_id', 'apply_date', 'applied_by', 'issue_date', 'valid_date', 'mail_to', 'mail_cc', 'notify_days', 'notified_for', 'lab_floor_id', 'active', 'remarks'];

    protected function casts(): array
    {
        return ['apply_date' => 'date', 'issue_date' => 'date', 'valid_date' => 'date', 'notified_for' => 'date', 'notify_days' => 'integer', 'active' => 'boolean'];
    }
}
