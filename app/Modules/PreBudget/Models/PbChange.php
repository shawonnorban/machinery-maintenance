<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One changed cell (or a created, completed or deleted row) of a requisition — the audit log. Pre-Budget (AnnotechERP 33/34).
 */
class PbChange extends BaseModel
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'pb_changes';

    protected $fillable = ['company_id', 'subject', 'subject_id', 'buyer_id', 'kind', 'column_key', 'old_value', 'new_value', 'batch', 'changed_by', 'changed_at'];

    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }
}
