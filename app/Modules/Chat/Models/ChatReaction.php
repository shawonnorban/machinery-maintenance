<?php

declare(strict_types=1);

namespace App\Modules\Chat\Models;

use App\Shared\Models\BaseModel;

/** One person's emoji on a message (one each; a new one replaces it). */
class ChatReaction extends BaseModel
{
    protected $table = 'chat_message_reactions';

    protected $fillable = ['message_id', 'user_id', 'emoji'];
}
