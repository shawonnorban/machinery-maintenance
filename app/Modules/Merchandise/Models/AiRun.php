<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** One "Run analysis": when, by which engine, what it read and found (Merchandise AI, AnnotechERP 06/02–06/04). */
class AiRun extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_ai_runs';

    protected $fillable = ['company_id', 'run_at', 'engine', 'orders', 'tasks', 'alerts', 'narrative', 'run_by'];

    protected function casts(): array
    {
        return ['run_at' => 'datetime', 'orders' => 'integer', 'tasks' => 'integer', 'alerts' => 'integer'];
    }
}
