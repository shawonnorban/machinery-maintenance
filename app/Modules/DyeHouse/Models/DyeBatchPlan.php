<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A board batches are laid out on: machines down the side, the clock across the top in steps of its scale (Dye Batch Plan). Dye House → Batch (Bulk) (AnnotechERP 17/03).
 */
class DyeBatchPlan extends BaseModel
{
    use BelongsToTenant;

    public const SCOPES = ['BULK', 'SAMPLE'];

    public const SCALES = [1, 2, 3, 4, 6, 8, 12, 24];

    protected $table = 'dye_batch_plans';

    protected $fillable = ['company_id', 'code', 'name', 'plan_date', 'party_id', 'scope', 'scale_hours', 'cell_width', 'window_from', 'remarks', 'active', 'created_by'];

    protected function casts(): array
    {
        return ['plan_date' => 'date', 'window_from' => 'datetime', 'active' => 'boolean', 'scale_hours' => 'integer', 'cell_width' => 'decimal:2'];
    }
}
