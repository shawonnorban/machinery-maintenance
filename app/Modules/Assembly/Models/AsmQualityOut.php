<?php

declare(strict_types=1);

namespace App\Modules\Assembly\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A judged bundle released from Quality to the next process, with what its inspections came to and its final status. Assembly Floor → Quality Management (AnnotechERP 20/03).
 */
class AsmQualityOut extends BaseModel
{
    use BelongsToTenant;

    public const FINAL_STATUSES = ['PASS', 'REJECT'];

    protected $table = 'asm_quality_outs';

    protected $fillable = ['company_id', 'quality_in_id', 'out_date', 'pass_qty', 'reject_qty', 'rework_qty', 'final_status', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['out_date' => 'date', 'pass_qty' => 'integer', 'reject_qty' => 'integer', 'rework_qty' => 'integer'];
    }
}
