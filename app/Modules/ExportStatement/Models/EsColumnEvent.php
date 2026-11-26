<?php

declare(strict_types=1);

namespace App\Modules\ExportStatement\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A change to an Export Statement column or its formula, with how many statements were worked out again (Column Setup history).
 */
class EsColumnEvent extends BaseModel
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'es_column_events';

    protected $fillable = ['company_id', 'column_key', 'action', 'before', 'after', 'recalculated', 'by', 'at'];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'recalculated' => 'integer', 'at' => 'datetime'];
    }
}
