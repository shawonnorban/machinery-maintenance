<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One part on a transfer.
 *
 * v1.0 modelled a transfer as a single from/to bin pair with no line items, so
 * a transfer could only ever move one part. Real inter-factory transfers move
 * many at once.
 */
class InventoryTransferItem extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'inventory_transfer_items';

    protected $fillable = [
        'company_id', 'inventory_transfer_id', 'spare_part_id', 'from_bin_id', 'to_bin_id',
        'quantity_requested', 'quantity_dispatched', 'quantity_received',
        'quantity_variance', 'unit_cost_at_dispatch', 'currency',
    ];

    protected function casts(): array
    {
        return [
            'quantity_requested' => MoneyCast::class,
            'quantity_dispatched' => MoneyCast::class,
            'quantity_received' => MoneyCast::class,
            'quantity_variance' => MoneyCast::class,
            'unit_cost_at_dispatch' => MoneyCast::class,
        ];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(InventoryTransfer::class, 'inventory_transfer_id');
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }

    public function fromBin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'from_bin_id');
    }

    public function toBin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'to_bin_id');
    }

    /**
     * Dispatched but never received. A non-zero variance drives a discrepancy
     * investigation rather than a silent write-off: the stock left one factory
     * and did not arrive, and somebody should find out where it went.
     */
    public function hasVariance(): bool
    {
        return bccomp((string) $this->quantity_variance, '0', 4) !== 0;
    }
}
