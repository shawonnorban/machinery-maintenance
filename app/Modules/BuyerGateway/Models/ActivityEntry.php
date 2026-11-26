<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One thing done from the buyer's side of the portal, timestamped. Buyer Gateway → Portal Activity Trail (AnnotechERP 25/05).
 */
class ActivityEntry extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'bg_activity_entries';

    protected $fillable = ['company_id', 'actor', 'buyer_id', 'action', 'target', 'occurred_on', 'occurred_time', 'device'];

    protected function casts(): array
    {
        return ['occurred_on' => 'date'];
    }
}
