<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A buyer's complaint or claim, with the shipment, cause and resolution. Buyer Gateway → Complaint & Claim Register (AnnotechERP 25/04).
 */
class Complaint extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'bg_complaints';

    protected $fillable = ['company_id', 'reference', 'buyer_id', 'shipment', 'cause', 'claim_value', 'raised_on', 'state', 'resolution'];

    protected function casts(): array
    {
        return ['claim_value' => 'float', 'raised_on' => 'date'];
    }
}
