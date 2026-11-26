<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A change to a requisition column or its formula, with how many rows were worked out again. Pre-Budget (AnnotechERP 33/34).
 */
class PbColumnEvent extends BaseModel
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'pb_column_events';

    protected $fillable = ['company_id', 'sheet', 'column_key', 'action', 'before', 'after', 'recalculated', 'by', 'at'];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'recalculated' => 'integer', 'at' => 'datetime'];
    }
}
