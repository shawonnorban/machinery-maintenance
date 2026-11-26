<?php

declare(strict_types=1);

namespace App\Modules\Assembly\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One of a packing batch's seven processes, in order: its status, verified quantity and who started and completed it. Assembly Floor → Packing Process (AnnotechERP 20/05).
 */
class AsmPackingStep extends BaseModel
{
    use BelongsToTenant;

    /** Brickland's seven packing processes, in the order a batch goes through them. */
    public const STEPS = ['FOLDING', 'POLY', 'LABEL', 'ASSORTMENT', 'CARTON', 'MARKING', 'SEALING'];

    public const STATUSES = ['PENDING', 'IN_PROGRESS', 'COMPLETED'];

    protected $table = 'asm_packing_steps';

    protected $fillable = ['company_id', 'batch_id', 'step', 'seq', 'status', 'qty', 'remarks', 'started_at', 'started_by', 'completed_at', 'completed_by', 'reopen_reason', 'reopened_at', 'reopened_by'];

    protected function casts(): array
    {
        return ['seq' => 'integer', 'qty' => 'integer', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'reopened_at' => 'datetime'];
    }
}
