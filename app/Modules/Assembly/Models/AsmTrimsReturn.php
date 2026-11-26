<?php

declare(strict_types=1);

namespace App\Modules\Assembly\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A Trims Receipt Return — items going back to the supplier against a completed receipt, off stock once completed. Assembly Floor → Accessories Store (AnnotechERP 20/08).
 */
class AsmTrimsReturn extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'COMPLETED', 'CANCELLED'];

    public const REASONS = ['SHORT', 'DAMAGED', 'EXCESS'];

    protected $table = 'asm_trims_returns';

    protected $fillable = ['company_id', 'return_no', 'return_date', 'receipt_id', 'reason', 'remarks', 'status', 'completed_at', 'completed_by', 'created_by'];

    protected function casts(): array
    {
        return ['return_date' => 'date', 'completed_at' => 'datetime'];
    }
}
