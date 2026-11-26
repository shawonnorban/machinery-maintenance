<?php

declare(strict_types=1);

namespace App\Modules\Chat\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A conversation: one to one (`direct_key`) or a group with a name. */
class Chat extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'chats';

    protected $fillable = ['company_id', 'is_group', 'name', 'description', 'image_file_id', 'direct_key', 'system_key', 'last_message_id', 'last_message_at', 'created_by'];

    protected function casts(): array
    {
        return ['is_group' => 'boolean', 'last_message_at' => 'datetime'];
    }

    public function members(): HasMany
    {
        return $this->hasMany(ChatMember::class, 'chat_id');
    }

    /** Members still in it (a group's leavers keep their row, with `left_at`). */
    public function activeMembers(): HasMany
    {
        return $this->members()->whereNull('left_at');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'chat_id');
    }

    /** The two people's ids, sorted: the same pair always finds the same chat. */
    public static function directKey(string $a, string $b): string
    {
        $pair = [$a, $b];
        sort($pair);

        return implode(':', $pair);
    }
}
