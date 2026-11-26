<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A message sent to everyone, a role or one user (Access Control → Global Notification Broadcast, AnnotechERP 02/04).
 */
class NotificationBroadcast extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['INFORMATION', 'WARNING', 'ALERT', 'MAINTENANCE'];

    public const AUDIENCES = ['ALL', 'ROLE', 'USER'];

    public const SEVERITIES = ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'];

    protected $table = 'notification_broadcasts';

    protected $fillable = ['company_id', 'title', 'notification_type', 'message', 'audience', 'audience_id', 'severity', 'message_time', 'offline_time', 'active', 'recipients_count', 'sent_by'];

    protected function casts(): array
    {
        return ['message_time' => 'date', 'offline_time' => 'date', 'active' => 'boolean', 'recipients_count' => 'integer'];
    }
}
