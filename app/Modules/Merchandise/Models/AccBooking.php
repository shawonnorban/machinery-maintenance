<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** An accessory booking raised against an order: a PO to one supplier, the trims it books below (Accessories & Trims, AnnotechERP 06/08). */
class AccBooking extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED', 'CANCELLED'];

    public const REQ_TYPES = ['BULK', 'SAMPLE', 'BUFFER'];

    protected $table = 'merch_acc_bookings';

    protected $fillable = ['company_id', 'booking_no', 'order_id', 'po_no', 'subject', 'supplier_id', 'req_type', 'po_date', 'delivery_date', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['po_date' => 'date', 'delivery_date' => 'date'];
    }
}
