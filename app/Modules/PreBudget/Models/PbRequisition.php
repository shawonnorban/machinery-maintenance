<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One requisition row — Knit (kg) or Woven (yds); every column's value lives
 * in `values`. A Draft until Completed, when it gets its serial number and
 * shows in All Requisition. Pre-Budget (AnnotechERP 33/34).
 */
class PbRequisition extends BaseModel
{
    use BelongsToTenant;
    use SoftDeletes;

    public const TYPES = ['KNIT', 'WOVEN'];

    public const DRAFT = 'DRAFT';

    public const COMPLETE = 'COMPLETE';

    protected $table = 'pb_requisitions';

    protected $fillable = ['company_id', 'serial_no', 'entry_type', 'status', 'buyer_id', 'os_order_id', 'values', 'version', 'completed_at', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['serial_no' => 'integer', 'values' => 'array', 'version' => 'integer', 'completed_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Identity\Models\User::class, 'created_by');
    }

    /** The Order Summary row it was made from (Add Requisition's order picker), if any. */
    public function order(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\OrderSummary\Models\OsOrder::class, 'os_order_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Identity\Models\User::class, 'updated_by');
    }
}
