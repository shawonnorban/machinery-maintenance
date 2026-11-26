<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** An open T&A task as one run ranked it, with the slip it forecasts and what to do (Merchandise AI, AnnotechERP 06/02–06/04). */
class AiTaskScore extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_ai_task_scores';

    protected $fillable = ['company_id', 'run_id', 'order_tna_id', 'order_id', 'rank', 'days_late', 'slip_days', 'slip_risk', 'priority', 'recommendation'];

    protected function casts(): array
    {
        return ['rank' => 'integer', 'days_late' => 'integer', 'slip_days' => 'integer', 'slip_risk' => 'float', 'priority' => 'float'];
    }
}
