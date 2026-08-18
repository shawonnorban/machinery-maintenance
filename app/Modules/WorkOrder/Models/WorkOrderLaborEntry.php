<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Models;

use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Time spent on a work order, converted to cost (ADR-050).
 *
 * This table is why actual_cost and every technician KPI have a source. v1.0
 * defined the cost field with nothing behind it, so the figure would have been
 * a number somebody typed.
 */
class WorkOrderLaborEntry extends BaseModel
{
    use BelongsToTenant;

    public const CATEGORIES = ['REGULAR', 'OVERTIME', 'EXTERNAL'];

    protected $table = 'work_order_labor_entries';

    protected $fillable = [
        'company_id', 'work_order_id', 'technician_id', 'labor_category',
        'labor_grade_id', 'vendor_id', 'started_at', 'ended_at', 'minutes',
        'hourly_rate', 'currency', 'exchange_rate', 'amount', 'base_amount',
        'notes', 'recorded_by',
    ];

    /** Millisecond precision, matching the column (ERD rule 15). */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'minutes' => 'integer',
            'hourly_rate' => MoneyCast::class,
            'amount' => MoneyCast::class,
            'base_amount' => MoneyCast::class,
        ];
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
