<?php

declare(strict_types=1);

namespace App\Modules\OrderSummary\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A change to a column or its formula, with how many rows were worked out again. Order Summary (AnnotechERP 32).
 */
class OsColumnEvent extends BaseModel
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'os_column_events';

    protected $fillable = ['company_id', 'column_key', 'action', 'before', 'after', 'recalculated', 'by', 'at'];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'recalculated' => 'integer', 'at' => 'datetime'];
    }
}
