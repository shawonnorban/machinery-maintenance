<?php

declare(strict_types=1);

namespace App\Modules\OrderSummary\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One changed cell (or a created / deleted row) — the audit log and the edit mail. Order Summary (AnnotechERP 32).
 */
class OsChange extends BaseModel
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'os_changes';

    protected $fillable = ['company_id', 'order_id', 'buyer_id', 'kind', 'column_key', 'old_value', 'new_value', 'batch', 'changed_by', 'changed_at', 'mailed_at'];

    protected function casts(): array
    {
        return ['changed_at' => 'datetime', 'mailed_at' => 'datetime'];
    }
}
