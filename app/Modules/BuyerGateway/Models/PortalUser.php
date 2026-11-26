<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * The buyer-side person who holds a portal login. Buyer Gateway → Portal User Accounts (AnnotechERP 25/01).
 */
class PortalUser extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'bg_portal_users';

    protected $fillable = ['company_id', 'buyer_id', 'name', 'email', 'role', 'country_id', 'last_seen', 'state', 'login_enabled', 'created_by'];

    protected function casts(): array
    {
        return ['login_enabled' => 'boolean', 'last_seen' => 'date'];
    }
}
