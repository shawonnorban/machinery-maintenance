<?php

declare(strict_types=1);

namespace App\Modules\QualityTally\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * How many people a line had on a day and shift, and its overtime minutes — what efficiency divides by. Quality Tally (AnnotechERP 36).
 */
class QtLineAttendance extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'qt_line_attendances';

    protected $fillable = ['company_id', 'att_date', 'section', 'line_id', 'shift_id', 'attendance', 'overtime_minutes', 'set_by'];

    protected function casts(): array
    {
        return ['att_date' => 'date', 'attendance' => 'integer', 'overtime_minutes' => 'integer'];
    }
}
