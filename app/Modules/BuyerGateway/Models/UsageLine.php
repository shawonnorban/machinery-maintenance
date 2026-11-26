<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A buyer's portal use in one month. Buyer Gateway → Portal Usage Insight (AnnotechERP 25/05).
 */
class UsageLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'bg_usage_lines';

    protected $fillable = ['company_id', 'buyer_id', 'period', 'logins', 'active_users', 'most_opened', 'downloads', 'avg_session', 'adoption'];

    protected function casts(): array
    {
        return ['logins' => 'integer', 'active_users' => 'integer', 'downloads' => 'integer', 'avg_session' => 'float', 'adoption' => 'float'];
    }
}
