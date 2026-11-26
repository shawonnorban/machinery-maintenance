<?php

declare(strict_types=1);

namespace App\Modules\Chat\Models;

use App\Modules\Identity\Models\User;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Files\Models\FileAttachment;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A message: text, an image or a file (with a caption), or a system line of
 * the group's own story (`meta.event`: created, added, removed, left, renamed,
 * admin, image). Deleted for everyone keeps the row (`deleted_at`) so the
 * chat shows "This message was deleted" where it stood.
 */
class ChatMessage extends BaseModel
{
    use BelongsToTenant;

    public const TEXT = 'TEXT';

    public const IMAGE = 'IMAGE';

    public const FILE = 'FILE';

    public const SYSTEM = 'SYSTEM';

    protected $table = 'chat_messages';

    protected $fillable = ['company_id', 'chat_id', 'user_id', 'type', 'body', 'file_id', 'reply_to_id', 'forwarded', 'meta', 'edited_at', 'deleted_at', 'pinned_at', 'pinned_by'];

    protected function casts(): array
    {
        return ['forwarded' => 'boolean', 'meta' => 'array', 'edited_at' => 'datetime', 'deleted_at' => 'datetime', 'pinned_at' => 'datetime'];
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class, 'chat_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(FileAttachment::class, 'file_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(ChatReaction::class, 'message_id');
    }
}
