<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Models;

use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemReservation;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One item on a fabric booking (Procurement spec §3). */
class FabricBookingLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'fabric_booking_lines';

    protected $fillable = [
        'company_id', 'fabric_booking_id', 'item_id', 'item_code', 'item_name',
        'uom_id', 'booked_qty', 'tentative_rate', 'required_date',
        'item_reservation_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'booked_qty' => MoneyCast::class,
            'tentative_rate' => MoneyCast::class,
            'required_date' => 'date',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(FabricBooking::class, 'fabric_booking_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    /** The claim this line staked when the booking was confirmed. */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(ItemReservation::class, 'item_reservation_id');
    }

    public function lineTotal(): string
    {
        return bcmul((string) $this->booked_qty, (string) ($this->tentative_rate ?? '0'), 4);
    }
}
