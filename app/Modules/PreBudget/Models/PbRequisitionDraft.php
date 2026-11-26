<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A Draft Requisition: the Add Requisition page as it was being typed — its
 * rows ({values, artwork_url}) kept as typed, nothing checked. Opened again
 * by its number; dropped once its requisitions are Completed (kept out of
 * sight, so its number is never given again). Pre-Budget
 * (AnnotechERP 33/34, Norban's draft).
 */
class PbRequisitionDraft extends BaseModel
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'pb_requisition_drafts';

    protected $fillable = ['company_id', 'draft_no', 'user_id', 'client_key', 'rows', 'row_count'];

    protected function casts(): array
    {
        return ['draft_no' => 'integer', 'rows' => 'array', 'row_count' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Identity\Models\User::class, 'user_id');
    }
}
