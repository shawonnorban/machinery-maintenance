<?php

declare(strict_types=1);

namespace App\Modules\Assembly\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A bundle scanned off its finishing line with its Good, Reject and Rework (adding up to what went on); the rework followed up to completion. Assembly Floor → Finishing (AnnotechERP 20/02).
 */
class AsmFinishingOut extends BaseModel
{
    use BelongsToTenant;

    public const REWORK_STATUSES = ['PENDING', 'PROCESSING', 'COMPLETED'];

    protected $table = 'asm_finishing_outs';

    protected $fillable = [
        'company_id', 'finishing_in_id', 'out_date', 'good_qty', 'reject_qty', 'rework_qty', 'rework_status',
        'rework_started_at', 'rework_started_by', 'rework_completed_at', 'rework_completed_by', 'created_by',
    ];

    protected function casts(): array
    {
        return ['out_date' => 'date', 'good_qty' => 'integer', 'reject_qty' => 'integer', 'rework_qty' => 'integer', 'rework_started_at' => 'datetime', 'rework_completed_at' => 'datetime'];
    }
}
