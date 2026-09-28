<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Models;

use App\Modules\Identity\Models\User;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One bill an advance was set against (Procurement spec §13).
 *
 * Its own row rather than a running balance, because "how much is left" and
 * "what did it go against" are different questions and only the second one
 * can be argued with. An adjustment made against the wrong receipt has to
 * be findable and reversible; a decremented balance is neither.
 */
class SupplierAdvanceAdjustment extends BaseModel
{
    use BelongsToTenant;

    /** Set against a bill, or handed back in cash. */
    public const TYPES = ['ADJUSTMENT', 'REFUND'];

    protected $table = 'supplier_advance_adjustments';

    protected $fillable = [
        'company_id', 'supplier_advance_id', 'goods_receipt_id', 'adjustment_type',
        'amount', 'reference_no', 'notes', 'adjusted_by', 'adjusted_at', 'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'adjusted_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function advance(): BelongsTo
    {
        return $this->belongsTo(SupplierAdvance::class, 'supplier_advance_id');
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function adjuster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }

    public function isLive(): bool
    {
        return $this->reversed_at === null;
    }
}
