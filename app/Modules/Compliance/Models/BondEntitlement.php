<?php

declare(strict_types=1);

namespace App\Modules\Compliance\Models;

use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\ItemMaster\Models\Item;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How much of one item a licence allows in duty-free (§3.1).
 *
 * There is deliberately no `consumed_quantity` here. What has actually
 * come in against the licence is a sum over goods receipts, and a stored
 * running total would be a second answer to a question the ledger already
 * answers — one that goes wrong the first time a receipt is cancelled.
 * `BondUtilisation` computes it instead.
 */
class BondEntitlement extends BaseModel
{
    use BelongsToTenant;

    /** A yearly ceiling, or an allowance against one export order. */
    public const BASES = ['ANNUAL', 'PER_ORDER'];

    protected $table = 'bond_entitlements';

    protected $fillable = [
        'company_id', 'bond_licence_id', 'item_id', 'item_code', 'item_name',
        'uom_id', 'basis', 'entitled_quantity', 'period_start', 'period_end', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'entitled_quantity' => MoneyCast::class,
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }

    public function licence(): BelongsTo
    {
        return $this->belongsTo(BondLicence::class, 'bond_licence_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }
}
