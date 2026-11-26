<?php

declare(strict_types=1);

namespace App\Modules\ExportStatement\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One changed cell (or a created or deleted statement) of an export statement — its audit log.
 */
class EsChange extends BaseModel
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'es_changes';

    protected $fillable = ['company_id', 'statement_id', 'buyer_id', 'kind', 'column_key', 'old_value', 'new_value', 'batch', 'changed_by', 'changed_at'];

    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }
}
