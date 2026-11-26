<?php

declare(strict_types=1);

namespace App\Modules\Approval\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A record waiting on somebody's signature (ERD Section 20).
 *
 * context_json is the important column. It freezes the cost, criticality and
 * factory the rules were evaluated against, so a later cost change never
 * retroactively alters what an approver was shown. Without it, an estimate
 * edited after approval makes "what did they actually agree to" unanswerable.
 */
class ApprovalRequest extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['PENDING', 'APPROVED', 'REJECTED', 'CANCELLED', 'EXPIRED', 'RETURNED'];

    protected $table = 'approval_requests';

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'company_id', 'workflow_id', 'entity_type', 'entity_id', 'status',
        'current_step', 'total_steps', 'requested_by', 'requested_at',
        'completed_at', 'expires_at', 'context_json',
        // Approval Center (AnnotechERP 04).
        'request_type_id', 'priority', 'due_at', 'request_to_user_id', 'escalated_at',
    ];

    protected function casts(): array
    {
        return [
            'context_json' => 'array',
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
            'due_at' => 'datetime',
            'escalated_at' => 'datetime',
            'current_step' => 'integer',
            'total_steps' => 'integer',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class, 'approval_request_id')
            ->orderBy('acted_at')
            ->orderBy('id');
    }

    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast() && $this->isPending();
    }

    /**
     * The steps this request actually has to go through, in order. Rules that
     * did not apply to the frozen context are not part of the chain at all.
     *
     * @return Collection<int, ApprovalRule>
     */
    public function applicableRules(): Collection
    {
        $rules = $this->workflow_id === null ? collect() : ApprovalRule::where('workflow_id', $this->workflow_id)
            ->where('active', true)
            ->where('level', '>=', 1)
            ->orderBy('sequence')
            ->get()
            ->filter(fn (ApprovalRule $rule) => $rule->appliesTo($this->context_json ?? []))
            ->values();

        // Request To is always level 1 (Approval Center, AnnotechERP 04): a
        // step that exists only on this request, naming the person the
        // requester chose. The chain's own levels follow it.
        if ($this->request_to_user_id !== null) {
            $rules->prepend(new ApprovalRule(['user_id' => $this->request_to_user_id, 'sequence' => 0, 'level' => 1]));
        }

        return $rules->values();
    }

    public function currentRule(): ?ApprovalRule
    {
        return $this->applicableRules()->get($this->current_step - 1);
    }
}
