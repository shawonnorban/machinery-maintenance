<?php

declare(strict_types=1);

namespace App\Modules\Chat\Models;

use App\Modules\Identity\Models\User;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person in a chat: their role, how far they have read and received
 * (message ids — ULIDs sort by time), and the chat pinned / muted / archived
 * for them, or cleared up to a message.
 */
class ChatMember extends BaseModel
{
    use BelongsToTenant;

    public const OWNER = 'OWNER';

    public const ADMIN = 'ADMIN';

    public const MEMBER = 'MEMBER';

    protected $table = 'chat_members';

    protected $fillable = ['company_id', 'chat_id', 'user_id', 'role', 'joined_at', 'left_at', 'synced_out', 'read_message_id', 'delivered_message_id', 'cleared_message_id', 'pinned_at', 'muted', 'archived'];

    protected function casts(): array
    {
        return ['joined_at' => 'datetime', 'left_at' => 'datetime', 'pinned_at' => 'datetime', 'muted' => 'boolean', 'archived' => 'boolean', 'synced_out' => 'boolean'];
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class, 'chat_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [self::OWNER, self::ADMIN], true);
    }
}
