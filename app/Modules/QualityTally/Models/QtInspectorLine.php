<?php

declare(strict_types=1);

namespace App\Modules\QualityTally\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A line given to a QC. Quality Tally (AnnotechERP 36).
 */
class QtInspectorLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'qt_inspector_lines';

    protected $fillable = ['company_id', 'inspector_id', 'section', 'line_id'];

    protected function casts(): array
    {
        return [];
    }
}
