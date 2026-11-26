<?php

declare(strict_types=1);

namespace App\Modules\DevSample\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A change to a Dev Sample column or its formula, with how many samples were worked out again (Column Setup history).
 */
class DsColumnEvent extends BaseModel
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'ds_column_events';

    protected $fillable = ['company_id', 'column_key', 'action', 'before', 'after', 'recalculated', 'by', 'at'];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'recalculated' => 'integer', 'at' => 'datetime'];
    }
}
