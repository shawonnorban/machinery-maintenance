<?php

declare(strict_types=1);

namespace App\Modules\QualityTally\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A line's target for one job on one day, with its working hours and manpower. Quality Tally (AnnotechERP 36).
 */
class QtLineTarget extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'qt_line_targets';

    protected $fillable = ['company_id', 'target_date', 'section', 'line_id', 'job_id', 'working_hours', 'manpower', 'target_qty', 'remarks', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['target_date' => 'date', 'working_hours' => 'decimal:2', 'manpower' => 'integer', 'target_qty' => 'integer'];
    }
}
