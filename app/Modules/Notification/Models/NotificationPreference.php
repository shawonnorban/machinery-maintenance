<?php

declare(strict_types=1);

namespace App\Modules\Notification\Models;

use App\Modules\Identity\Models\User;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How loudly one person wants to be told about one kind of event (SRS 27).
 *
 * In-app is not switchable. The record of what happened is part of the audit
 * trail rather than a preference; the other channels decide whether somebody
 * is interrupted for it.
 */
class NotificationPreference extends BaseModel
{
    use BelongsToTenant;

    public const CHANNELS = ['in_app', 'email', 'sms', 'whatsapp'];

    protected $table = 'notification_preferences';

    protected $fillable = [
        'company_id', 'user_id', 'event_type', 'in_app', 'email', 'sms', 'whatsapp',
    ];

    protected function casts(): array
    {
        return [
            'in_app' => 'boolean',
            'email' => 'boolean',
            'sms' => 'boolean',
            'whatsapp' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return list<string>
     */
    public function enabledChannels(): array
    {
        $channels = ['IN_APP'];

        foreach (['email' => 'EMAIL', 'sms' => 'SMS', 'whatsapp' => 'WHATSAPP'] as $field => $channel) {
            if ($this->{$field}) {
                $channels[] = $channel;
            }
        }

        return $channels;
    }
}
