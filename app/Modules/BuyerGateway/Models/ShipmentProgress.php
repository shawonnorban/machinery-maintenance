<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A shipment against the buyer's agreed date. Buyer Gateway → Shipment Progress View (AnnotechERP 25/02).
 */
class ShipmentProgress extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'bg_shipment_progress';

    protected $fillable = ['company_id', 'order_no', 'buyer_id', 'mode', 'stage', 'agreed_date', 'actual_date', 'quantity', 'cartons'];

    protected function casts(): array
    {
        return ['agreed_date' => 'date', 'actual_date' => 'date', 'quantity' => 'integer', 'cartons' => 'integer'];
    }
}
