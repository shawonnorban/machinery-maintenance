<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Fabric booked straight against an order, with no knitting run behind it.
 */
class KnitDirectBooking extends BaseModel
{
    use BelongsToTenant;

    /** Draft → Submitted → Approved (released to the fabric store); Cancelled. */
    public const STATUSES = ['DRAFT', 'SUBMITTED', 'APPROVED', 'CANCELLED'];

    protected $table = 'knit_direct_bookings';

    protected $fillable = ['company_id', 'booking_no', 'category_code', 'buyer_id', 'style_id', 'order_id', 'prog_date', 'remarks', 'status', 'decided_at', 'decided_by', 'created_by'];

    protected function casts(): array
    {
        return ['prog_date' => 'date', 'decided_at' => 'datetime'];
    }
}
