<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How many of a part are in one bin, and what they are worth.
 *
 * Never written directly. Every change goes through InventoryLedger inside the
 * same database transaction as the ledger row that caused it, under a row lock
 * — otherwise two concurrent issues of the last item both read "1 available".
 */
class InventoryBalance extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'inventory_balances';

    protected $fillable = [
        'company_id', 'spare_part_id', 'bin_id', 'quantity_on_hand',
        'quantity_reserved', 'weighted_average_cost', 'currency', 'version',
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => MoneyCast::class,
            'quantity_reserved' => MoneyCast::class,
            'weighted_average_cost' => MoneyCast::class,
            'version' => 'integer',
        ];
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class);
    }

    /** On hand minus reserved. Reserved stock is already promised elsewhere. */
    public function available(): string
    {
        return bcsub(
            (string) $this->quantity_on_hand,
            (string) $this->quantity_reserved,
            4,
        );
    }

    public function totalValue(): string
    {
        return bcmul((string) $this->quantity_on_hand, (string) $this->weighted_average_cost, 4);
    }
}
