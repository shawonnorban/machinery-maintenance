<?php

declare(strict_types=1);

namespace App\Modules\Notification\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to reach somebody through one channel.
 *
 * Recorded separately from the notification so a failure is visible as a
 * failure. "The email bounced" and "nobody was told" are different problems,
 * and a system that cannot tell them apart cannot be debugged when somebody
 * says they never heard.
 */
class NotificationDelivery extends BaseModel
{
    use BelongsToTenant;

    public const CHANNELS = ['IN_APP', 'BROADCAST', 'EMAIL', 'SMS', 'WHATSAPP'];

    public const STATUSES = ['PENDING', 'SENT', 'FAILED', 'SKIPPED'];

    protected $table = 'notification_deliveries';

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'company_id', 'notification_id', 'channel', 'status', 'sent_at', 'failure_reason',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }
}
