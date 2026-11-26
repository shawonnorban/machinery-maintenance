<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * How one portal event reaches one buyer: channel, digest and recipients. Buyer Gateway → Notification Settings (AnnotechERP 25/01).
 */
class PortalNotificationPref extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'bg_notification_prefs';

    protected $fillable = ['company_id', 'buyer_id', 'event', 'channel', 'digest', 'recipients', 'enabled', 'set_on'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'set_on' => 'date'];
    }
}
