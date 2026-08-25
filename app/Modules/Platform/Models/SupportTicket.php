<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

use App\Modules\Identity\Models\User;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer asking the platform something, in writing.
 *
 * Tenant-scoped in the ordinary way: a customer's own screen sees only their
 * tickets because every other tenant-owned table works that way, and platform
 * staff reach across every company's tickets the same way they reach every
 * other platform screen — withoutGlobalScope(TenantScope::class).
 */
class SupportTicket extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED'];

    /** A customer can still write into these; CLOSED is the end of the line. */
    public const OPEN_STATUSES = ['OPEN', 'IN_PROGRESS', 'RESOLVED'];

    protected $table = 'support_tickets';

    protected $fillable = [
        'company_id', 'opened_by', 'assigned_to', 'subject', 'status',
        'last_message_at', 'resolved_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class, 'ticket_id')->orderBy('created_at');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }
}
