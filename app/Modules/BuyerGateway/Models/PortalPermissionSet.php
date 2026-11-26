<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * What one portal role may open, download and act on for one buyer. Buyer Gateway → Portal Access Permissions (AnnotechERP 25/01).
 */
class PortalPermissionSet extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'bg_permission_sets';

    protected $fillable = ['company_id', 'buyer_id', 'role', 'orders', 'samples', 'documents', 'commercial', 'may_approve', 'set_on'];

    protected function casts(): array
    {
        return ['may_approve' => 'boolean', 'set_on' => 'date'];
    }
}
