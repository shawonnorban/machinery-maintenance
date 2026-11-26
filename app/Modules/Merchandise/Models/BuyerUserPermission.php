<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A user allowed to work a buyer account, for merchandising or for marketing (Buyer Account Permission). Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class BuyerUserPermission extends BaseModel
{
    use BelongsToTenant;

    public const KINDS = ['MERCHANDISING', 'MARKETING'];

    protected $table = 'buyer_user_permissions';

    protected $fillable = ['company_id', 'kind', 'user_id', 'buyer_id'];
}
