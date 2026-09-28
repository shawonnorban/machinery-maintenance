<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a lot number means (Inventory spec §12: yarn lot-wise, chemical
 * expiry).
 *
 * The stock ledger already keeps a balance per lot, but a lot number on its
 * own is just a string — it cannot say when the dye goes off or when the
 * yarn was spun. This is the small record that can: one row per (item, lot),
 * written when the lot is first received.
 *
 * Deliberately thin, and deliberately not the balance: quantities stay in
 * `item_stock_balances` where the ledger owns them. Putting a quantity here
 * would give the same number two homes and a way to disagree.
 */
class ItemLot extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'item_lots';

    protected $fillable = [
        'company_id', 'item_id', 'lot_no', 'manufactured_date', 'expiry_date',
        'first_received_date', 'supplier_lot_no', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'manufactured_date' => 'date',
            'expiry_date' => 'date',
            'first_received_date' => 'date',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function hasExpired(?CarbonImmutable $at = null): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->lt($at ?? CarbonImmutable::today());
    }

    /**
     * Negative once it is past. Null when the lot has no expiry at all,
     * which is most of them — only chemicals and a few trims do.
     */
    public function daysToExpiry(?CarbonImmutable $at = null): ?int
    {
        if ($this->expiry_date === null) {
            return null;
        }

        return (int) ($at ?? CarbonImmutable::today())->startOfDay()->diffInDays($this->expiry_date->startOfDay(), false);
    }
}
