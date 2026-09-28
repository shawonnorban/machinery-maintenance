<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Vendor\Models\Vendor;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A goods receipt note (Procurement spec §9).
 *
 * One receipt may cover several purchase orders and one order may arrive
 * across several receipts; the lines carry the link. Stock is posted to the
 * ledger only on COMPLETED — until then the lines say what came through the
 * gate, not what is on the shelf.
 */
class GoodsReceipt extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['LOCAL', 'IMPORT', 'SUBCONTRACT_RETURN'];

    public const STATUSES = ['DRAFT', 'UNDER_INSPECTION', 'COMPLETED', 'CANCELLED'];

    protected $table = 'goods_receipts';

    /**
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        'DRAFT' => ['UNDER_INSPECTION', 'COMPLETED', 'CANCELLED'],
        'UNDER_INSPECTION' => ['COMPLETED', 'CANCELLED'],
        'COMPLETED' => [],
        'CANCELLED' => [],
    ];

    protected $fillable = [
        'company_id', 'factory_id', 'store_id', 'vendor_id', 'po_id', 'grn_no', 'grn_date',
        'gate_pass_no', 'grn_type', 'status', 'notes', 'created_by', 'completed_at', 'landed_cost_posted_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return ['grn_date' => 'date', 'completed_at' => 'datetime',
            'landed_cost_posted_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isEditable(): bool
    {
        return $this->status === 'DRAFT' && ! $this->isRateLocked();
    }

    /**
     * Landed cost has been posted against this receipt, so its rates are
     * part of a valuation somebody has already reported (spec §11). A
     * correction is a second landed cost voucher, not an edit here.
     */
    public function isRateLocked(): bool
    {
        return $this->landed_cost_posted_at !== null;
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * What the delivery is worth at the rates on it — the provisional
     * figure the books take at the gate (Shared Systems §2), before any
     * landed cost moves it.
     */
    public function totalValue(): string
    {
        $this->loadMissing('lines');

        return $this->lines->reduce(
            fn (string $sum, GoodsReceiptLine $line): string => bcadd(
                $sum,
                bcmul((string) $line->received_qty, (string) ($line->rate ?? '0'), 4),
                4,
            ),
            '0.0000',
        );
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'COMPLETED';
    }
}
