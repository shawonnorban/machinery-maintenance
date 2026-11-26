<?php

declare(strict_types=1);

namespace App\Modules\ColourLab\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A chemical trial in a lab: which dyes and chemicals were tried, at what dose and cost, and how each came out. Colour Lab → Operations (AnnotechERP 16/01).
 */
class LabTrial extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['PENDING', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'];

    /** Still being worked: its lines may change. */
    public const OPEN = ['PENDING', 'IN_PROGRESS'];

    protected $table = 'lab_trials';

    protected $fillable = ['company_id', 'trial_no', 'lab_floor_id', 'trial_date', 'trial_by', 'status', 'started_at', 'completed_at', 'created_by'];

    protected function casts(): array
    {
        return ['trial_date' => 'date', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
