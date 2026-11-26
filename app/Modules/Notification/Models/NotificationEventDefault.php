<?php

declare(strict_types=1);

namespace App\Modules\Notification\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * The company's own default channels for one event (Settings → Notification
 * Preferences, AnnotechERP 03/04). A person's own preference still wins —
 * unless the event is locked, when the company's choice applies to everybody.
 */
class NotificationEventDefault extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'notification_event_defaults';

    protected $fillable = ['company_id', 'event_type', 'email', 'sms', 'whatsapp', 'locked'];

    protected function casts(): array
    {
        return ['email' => 'boolean', 'sms' => 'boolean', 'whatsapp' => 'boolean', 'locked' => 'boolean'];
    }
}
