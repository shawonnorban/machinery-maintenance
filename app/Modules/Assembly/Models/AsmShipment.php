<?php

declare(strict_types=1);

namespace App\Modules\Assembly\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A shipment of one PO and colour out of Ready for Shipment: planned, confirmed, shipped, cancelled or reversed. Assembly Floor → Shipment (AnnotechERP 20/07).
 */
class AsmShipment extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['PLANNED', 'CONFIRMED', 'SHIPPED', 'CANCELLED', 'REVERSED'];

    public const OPEN = ['PLANNED', 'CONFIRMED'];

    public const MODES = ['SEA', 'AIR', 'ROAD'];

    protected $table = 'asm_shipments';

    protected $fillable = ['company_id', 'shipment_no', 'order_id', 'po_no', 'buyer_id', 'style', 'colour', 'qty', 'carton_qty', 'ship_date', 'destination', 'mode', 'vehicle_ref', 'remarks',
        'status', 'ready_before', 'remaining_after', 'confirmed_at', 'confirmed_by', 'shipped_at', 'shipped_by', 'cancelled_at', 'cancelled_by', 'cancel_reason',
        'reversed_at', 'reversed_by', 'reverse_reason', 'corrects_id', 'created_by'];

    protected function casts(): array
    {
        return ['qty' => 'integer', 'carton_qty' => 'integer', 'ship_date' => 'date', 'ready_before' => 'integer', 'remaining_after' => 'integer',
            'confirmed_at' => 'datetime', 'shipped_at' => 'datetime', 'cancelled_at' => 'datetime', 'reversed_at' => 'datetime'];
    }
}
