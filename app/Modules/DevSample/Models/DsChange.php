<?php

declare(strict_types=1);

namespace App\Modules\DevSample\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One changed cell (or a created or deleted sample) of a development sample — its audit log.
 */
class DsChange extends BaseModel
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'ds_changes';

    protected $fillable = ['company_id', 'sample_id', 'buyer_id', 'kind', 'column_key', 'old_value', 'new_value', 'batch', 'changed_by', 'changed_at'];

    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }
}
