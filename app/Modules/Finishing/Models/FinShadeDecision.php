<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A finished batch's shade, GSM and width decision against the buyer's approved standard — one per batch, who decided and when. Finishing Line → QC Process (AnnotechERP 18/04).
 */
class FinShadeDecision extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['PENDING', 'APPROVED', 'REJECTED'];

    protected $table = 'fin_shade_decisions';

    protected $fillable = ['company_id', 'receipt_id', 'buyer_id', 'order_id', 'style', 'colour', 'lot_no', 'batch_qty', 'model_continuity', 'status', 'decided_by', 'decided_at', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['batch_qty' => 'decimal:3', 'decided_at' => 'datetime'];
    }
}
