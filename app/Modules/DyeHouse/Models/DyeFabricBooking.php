<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Direct (DFB-) or General (GFB-) Fabric Booking: fabric booked to the dye house by fabric and shade, against one order or none. Dye House → Production Board (AnnotechERP 17/07).
 */
class DyeFabricBooking extends BaseModel
{
    use BelongsToTenant;

    public const KINDS = ['DIRECT', 'GENERAL'];

    public const PROD_TYPES = ['BULK', 'SAMPLE'];

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'APPROVED', 'CLOSED', 'CANCELLED'];

    protected $table = 'dye_fabric_bookings';

    protected $fillable = ['company_id', 'kind', 'booking_no', 'prod_type', 'prog_date', 'buyer_id', 'order_id', 'req_type', 'status', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['prog_date' => 'date'];
    }
}
