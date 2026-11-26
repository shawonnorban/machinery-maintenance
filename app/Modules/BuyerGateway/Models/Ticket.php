<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A portal problem a buyer reported, with owner, priority and time to close. Buyer Gateway → Support Ticket Desk (AnnotechERP 25/04).
 */
class Ticket extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'bg_tickets';

    protected $fillable = ['company_id', 'reference', 'subject', 'buyer_id', 'priority', 'owner_id', 'raised_on', 'state', 'resolved_at'];

    protected function casts(): array
    {
        return ['raised_on' => 'date', 'resolved_at' => 'datetime'];
    }
}
