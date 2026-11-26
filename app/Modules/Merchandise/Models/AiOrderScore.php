<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A live order as one run scored it: composite risk, health, delay forecast, four sub-scores, the reasons and the next move (Merchandise AI, AnnotechERP 06/02–06/04). */
class AiOrderScore extends BaseModel
{
    use BelongsToTenant;

    public const BANDS = ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'];

    protected $table = 'merch_ai_order_scores';

    protected $fillable = ['company_id', 'run_id', 'order_id', 'risk', 'health', 'band', 'delay_probability', 'expected_delay', 'confidence', 'material_risk', 'production_risk', 'approval_risk', 'tna_risk', 'shipment_risk', 'production_progress', 'task_completion', 'tasks_total', 'tasks_done', 'next_milestone', 'next_milestone_date', 'factors', 'action', 'action_source', 'summary'];

    protected function casts(): array
    {
        return ['risk' => 'float', 'health' => 'float', 'delay_probability' => 'float', 'expected_delay' => 'integer', 'confidence' => 'integer', 'material_risk' => 'float', 'production_risk' => 'float', 'approval_risk' => 'float', 'tna_risk' => 'float', 'shipment_risk' => 'float', 'production_progress' => 'float', 'task_completion' => 'float', 'tasks_total' => 'integer', 'tasks_done' => 'integer', 'next_milestone_date' => 'date', 'factors' => 'array'];
    }
}
