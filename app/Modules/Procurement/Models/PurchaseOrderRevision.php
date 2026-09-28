<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A purchase order as it stood before one revision (Procurement spec §7).
 *
 * Whole-document snapshot rather than a diff: the question asked of it is
 * "what did revision 2 say", and a diff answers that only by replaying.
 */
class PurchaseOrderRevision extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'purchase_order_revisions';

    protected $fillable = [
        'company_id', 'purchase_order_id', 'revision_no', 'reason', 'snapshot_json', 'revised_by', 'revised_at',
    ];

    protected function casts(): array
    {
        return [
            'revision_no' => 'integer',
            'snapshot_json' => 'array',
            'revised_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }
}
