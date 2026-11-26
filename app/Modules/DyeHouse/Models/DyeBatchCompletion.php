<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Batch Programme Completion: a batch programme requisition marked finished once every QC gate has passed. Dye House → Dyeing Q.C (AnnotechERP 17/08).
 */
class DyeBatchCompletion extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_batch_completions';

    protected $fillable = ['company_id', 'requisition_id', 'remarks', 'completed_by'];

    protected function casts(): array
    {
        return [];
    }
}
