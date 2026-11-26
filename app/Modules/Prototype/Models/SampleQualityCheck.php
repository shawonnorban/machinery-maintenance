<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * The internal check a finished sample passes before approval, against its
 * current version (QC-2026-00001), checkpoint by checkpoint. The verdict
 * moves the sample: Pass or Pass With Comment up for approval, Fail kept in
 * Quality, Rework Required back to production.
 */
class SampleQualityCheck extends BaseModel
{
    use BelongsToTenant;

    public const RESULTS = ['PASS', 'PASS_WITH_COMMENT', 'FAIL', 'REWORK_REQUIRED'];

    public const PASSED = ['PASS', 'PASS_WITH_COMMENT'];

    public const SEVERITIES = ['MINOR', 'MAJOR', 'CRITICAL'];

    /** The checkpoints, in the order the checklist reads. */
    public const CHECKPOINTS = ['MEASUREMENT', 'FABRIC', 'COLOR', 'CONSTRUCTION', 'STITCH', 'SEAM', 'PRINT', 'EMBROIDERY', 'LABEL', 'ACCESSORIES', 'FINISHING', 'APPEARANCE', 'PACKING'];

    public const CHECKPOINT_RESULTS = ['OK', 'NOT_OK', 'NA'];

    protected $table = 'proto_quality_checks';

    protected $fillable = ['company_id', 'qc_no', 'sample_id', 'version', 'checked_by', 'check_date', 'result', 'severity', 'defect_type', 'defect', 'correction', 'remarks', 'attachment'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'check_date' => 'date'];
    }
}
