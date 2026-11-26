<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * What a buyer asks for through the portal, routed to the desk that answers it. Buyer Gateway → Buyer Request Inbox (AnnotechERP 25/04).
 */
class BuyerRequest extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'bg_requests';

    protected $fillable = ['company_id', 'subject', 'buyer_id', 'kind', 'routed_to', 'raised_on', 'priority', 'state', 'closed_on'];

    protected function casts(): array
    {
        return ['raised_on' => 'date', 'closed_on' => 'date'];
    }
}
