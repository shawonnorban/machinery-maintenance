<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Models;

use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One roll out at a subcontractor (Inventory spec §8).
 *
 * `sent_qty` is what left; `returned_qty` what came back as cloth; and
 * `wastage_qty` the difference the subcontractor accounts for — shrinkage
 * in dyeing, trimmed selvedge in printing. The three must add up before
 * the line is settled, because "we sent 100 and got 94 back" with nothing
 * said about the other six is how a process loss becomes invisible.
 */
class SubcontractChallanLine extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['OUT', 'PARTIALLY_RETURNED', 'RETURNED', 'WRITTEN_OFF'];

    protected $table = 'subcontract_challan_lines';

    protected $fillable = [
        'company_id', 'subcontract_challan_id', 'fabric_roll_id', 'item_id',
        'roll_no', 'sent_qty', 'returned_qty', 'wastage_qty', 'status',
        'returned_roll_id', 'quality_status', 'returned_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'sent_qty' => 'decimal:2',
            'returned_qty' => 'decimal:2',
            'wastage_qty' => 'decimal:2',
            'returned_at' => 'datetime',
        ];
    }

    public function challan(): BelongsTo
    {
        return $this->belongsTo(SubcontractChallan::class, 'subcontract_challan_id');
    }

    public function roll(): BelongsTo
    {
        return $this->belongsTo(FabricRoll::class, 'fabric_roll_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** Still unaccounted for: neither returned nor written off as wastage. */
    public function outstandingQty(): string
    {
        $accounted = bcadd((string) $this->returned_qty, (string) $this->wastage_qty, 2);
        $outstanding = bcsub((string) $this->sent_qty, $accounted, 2);

        return bccomp($outstanding, '0', 2) === 1 ? $outstanding : '0.00';
    }

    public function isSettled(): bool
    {
        return bccomp($this->outstandingQty(), '0', 2) === 0;
    }
}
