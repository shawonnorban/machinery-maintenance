<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Models;

use App\Modules\Maintenance\Models\ChecklistItem;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One answer on an executed checklist (SRS 12).
 *
 * References the item on the frozen template version the work order
 * snapshotted, so a work order closed two years ago still reproduces exactly
 * what its technician worked through.
 */
class WorkOrderChecklistResult extends BaseModel
{
    use BelongsToTenant;

    public const RESULTS = ['PASS', 'FAIL', 'NA'];

    protected $table = 'work_order_checklist_results';

    protected $fillable = [
        'company_id', 'work_order_id', 'checklist_item_id', 'result',
        'numeric_value', 'text_value', 'observation', 'file_id',
        'is_within_tolerance', 'followup_work_order_id',
        'completed_by', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'is_within_tolerance' => 'boolean',
            'numeric_value' => MoneyCast::class,
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class, 'checklist_item_id');
    }

    public function isFailure(): bool
    {
        return $this->result === 'FAIL';
    }
}
