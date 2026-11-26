<?php

declare(strict_types=1);

namespace App\Modules\Settings\Models;

use App\Shared\Models\BaseModel;

/**
 * One run of a scheduled command (Settings → Scheduled Jobs, AnnotechERP 03/04).
 * Global: a command runs for every company at once. No output is kept, since
 * it could mention any of them.
 */
class ScheduledJobRun extends BaseModel
{
    public const STATUSES = ['RUNNING', 'SUCCEEDED', 'FAILED'];

    protected $table = 'scheduled_job_runs';

    protected $fillable = ['command', 'expression', 'status', 'exit_code', 'started_at', 'finished_at', 'duration_ms'];

    protected function casts(): array
    {
        return ['exit_code' => 'integer', 'duration_ms' => 'integer', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}
