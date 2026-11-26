<?php

declare(strict_types=1);

namespace App\Modules\Chat\Models;

use App\Shared\Models\BaseModel;

/**
 * A browser that takes chat notifications while it is closed (Web Push).
 * Not tenant-scoped on purpose: the sender looks rows up by user and company
 * itself, and a browser signed in elsewhere is moved, not duplicated.
 */
class ChatPushSubscription extends BaseModel
{
    protected $table = 'chat_push_subscriptions';

    protected $fillable = ['company_id', 'user_id', 'endpoint_hash', 'endpoint', 'p256dh', 'auth', 'user_agent', 'last_sent_at'];

    protected function casts(): array
    {
        return ['last_sent_at' => 'datetime'];
    }
}
