<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A new buyer on its way from signed account to working portal login. Buyer Gateway → Buyer Onboarding Workflow (AnnotechERP 25/01).
 */
class PortalOnboarding extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'bg_onboardings';

    protected $fillable = ['company_id', 'buyer_name', 'step', 'owner_id', 'started_on', 'target_live', 'progress', 'users_invited', 'state'];

    protected function casts(): array
    {
        return ['started_on' => 'date', 'target_live' => 'date', 'progress' => 'integer', 'users_invited' => 'integer'];
    }
}
