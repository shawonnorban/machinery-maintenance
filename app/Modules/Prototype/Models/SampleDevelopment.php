<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Pattern to finishing for one version of a sample: who makes it, the
 * fourteen-point checklist and its progress. Completed — every applicable
 * point done, or the status set — it is closed and locked.
 */
class SampleDevelopment extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['PENDING', 'IN_PROGRESS', 'ON_HOLD', 'COMPLETED', 'DELAYED', 'CANCELLED'];

    /** The fourteen development points, in the order the checklist reads. */
    public const TASKS = ['PATTERN', 'FABRIC', 'COLOR', 'MEASUREMENT', 'ACCESSORIES', 'LABEL', 'PRINT', 'EMBROIDERY', 'WASH', 'CONSTRUCTION', 'CUTTING', 'SEWING', 'FINISHING', 'QUALITY'];

    public const TASK_STATUSES = ['PENDING', 'IN_PROGRESS', 'COMPLETED', 'NOT_REQUIRED'];

    protected $table = 'proto_developments';

    protected $fillable = [
        'company_id', 'dev_no', 'sample_id', 'version', 'status', 'progress', 'dev_date', 'start_date', 'expected_completion', 'actual_completion',
        'pattern_master', 'sample_maker', 'technician', 'supervisor', 'delay_reason', 'remarks',
    ];

    protected function casts(): array
    {
        return ['version' => 'integer', 'progress' => 'float', 'dev_date' => 'date', 'start_date' => 'date', 'expected_completion' => 'date', 'actual_completion' => 'date'];
    }

    public function isLocked(): bool
    {
        return in_array($this->status, ['COMPLETED', 'CANCELLED'], true);
    }
}
