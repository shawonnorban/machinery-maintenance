<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Models;

use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\ItemMaster\Models\Item;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One agreed price, for one item, above one quantity (Procurement §14).
 *
 * `min_quantity` is what lets a contract hold a price break. Without it
 * the only way to say "cheaper above 500 metres" is a second contract for
 * the same item over the same dates — and then two agreements cover one
 * day and nothing can say which of them is the price.
 */
class RateContractLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'rate_contract_lines';

    protected $fillable = [
        'company_id', 'rate_contract_id', 'item_id', 'item_code', 'item_name',
        'uom_id', 'rate', 'min_quantity', 'lead_time_days', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'rate' => MoneyCast::class,
            'min_quantity' => MoneyCast::class,
            'lead_time_days' => 'integer',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(RateContract::class, 'rate_contract_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    /** Whether an order of this size reaches this band. */
    public function coversQuantity(string $quantity): bool
    {
        return bccomp($quantity, (string) $this->min_quantity, 4) >= 0;
    }
}
