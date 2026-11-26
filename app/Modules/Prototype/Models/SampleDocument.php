<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A file attached to a sample (SDC-2026-00001): a new one of a type the sample already has is its next version, the earlier ones kept. */
class SampleDocument extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['TECH_PACK', 'MEASUREMENT_SHEET', 'BUYER_COMMENT', 'REFERENCE_IMAGE', 'FABRIC_IMAGE', 'PATTERN', 'BOM', 'APPROVAL_SHEET', 'QC_REPORT', 'COURIER_DOCUMENT', 'BUYER_APPROVAL', 'OTHER'];

    protected $table = 'proto_documents';

    protected $fillable = ['company_id', 'doc_no', 'sample_id', 'doc_type', 'version', 'title', 'file', 'file_id', 'file_name', 'file_size', 'remarks', 'uploaded_by'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'file_size' => 'integer'];
    }
}
