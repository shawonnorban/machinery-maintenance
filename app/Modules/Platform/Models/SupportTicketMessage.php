<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

use App\Modules\Identity\Models\User;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message in a ticket thread. Append-only: a correction is a new message,
 * not an edit to an old one, matching every other thread in the product.
 */
class SupportTicketMessage extends BaseModel
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'support_ticket_messages';

    protected $fillable = ['ticket_id', 'company_id', 'author_id', 'author_is_platform', 'body', 'created_at'];

    protected function casts(): array
    {
        return [
            'author_is_platform' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
