<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Models;

use App\Modules\Identity\Models\User;
use App\Modules\ItemMaster\Models\FabricRoll;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Vendor\Models\Vendor;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money claimed from a supplier for goods that cannot go back
 * (Procurement spec §12).
 *
 * A return and a claim answer the same complaint in two different ways. If
 * the cloth is still on the shelf it goes back and the supplier is debited
 * for it. If it has been cut, dyed, sewn into a garment or simply used,
 * there is nothing to put on a lorry — but the loss is just as real, so
 * what is claimed is money rather than goods.
 *
 * That is why a claim touches no stock at all. Nothing moves; the factory
 * keeps what it has and asks to be compensated. The settlement is tracked
 * because these are rarely paid in full: a mill offers a credit note for
 * part of it, the rest is argued about, and "what did we actually recover"
 * is the question the scorecard is built from.
 */
class SupplierClaim extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['RAISED', 'ACCEPTED', 'PARTIALLY_SETTLED', 'SETTLED', 'REJECTED', 'WITHDRAWN'];

    public const REASONS = ['QUALITY', 'SHORT_SHIPMENT', 'DAMAGE_IN_TRANSIT', 'PRICE_DIFFERENCE', 'OTHER'];

    /**
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        // The supplier can accept it, reject it, or start paying straight
        // away — a partial credit note is itself an acceptance.
        'RAISED' => ['ACCEPTED', 'PARTIALLY_SETTLED', 'SETTLED', 'REJECTED', 'WITHDRAWN'],
        'ACCEPTED' => ['PARTIALLY_SETTLED', 'SETTLED', 'WITHDRAWN'],
        'PARTIALLY_SETTLED' => ['PARTIALLY_SETTLED', 'SETTLED', 'WITHDRAWN'],
        'SETTLED' => [],
        'REJECTED' => [],
        'WITHDRAWN' => [],
    ];

    protected $table = 'supplier_claims';

    protected $fillable = [
        'company_id', 'factory_id', 'claim_no', 'claim_date', 'vendor_id',
        'goods_receipt_id', 'purchase_return_id', 'fabric_roll_id', 'item_id',
        'reason', 'status', 'claim_amount', 'settled_amount', 'currency',
        'description', 'settlement_reference', 'notes', 'created_by',
        'accepted_at', 'settled_at', 'rejected_at', 'withdrawn_at',
    ];

    protected function casts(): array
    {
        return [
            'claim_date' => 'date',
            'claim_amount' => MoneyCast::class,
            'settled_amount' => MoneyCast::class,
            'accepted_at' => 'datetime',
            'settled_at' => 'datetime',
            'rejected_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    public function fabricRoll(): BelongsTo
    {
        return $this->belongsTo(FabricRoll::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /** Still being argued about. */
    public function isOpen(): bool
    {
        return in_array($this->status, ['RAISED', 'ACCEPTED', 'PARTIALLY_SETTLED'], true);
    }

    /** What the supplier still owes on this claim. */
    public function outstanding(): string
    {
        $outstanding = bcsub((string) $this->claim_amount, (string) $this->settled_amount, 4);

        return bccomp($outstanding, '0', 4) === 1 ? $outstanding : '0.0000';
    }
}
