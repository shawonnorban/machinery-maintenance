<?php

declare(strict_types=1);

namespace App\Modules\Approval\Models;

use App\Modules\Approval\Contracts\Approvable;
use App\Modules\Identity\Models\User;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request written by hand in the Approval Center (New Request, AnnotechERP
 * 04/01): the buyer, order and style, what is asked and whom it goes to. Routed
 * by the same engine as every module's documents, as entity MANUAL_REQUEST.
 */
class ApprovalTicket extends BaseModel implements Approvable
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'PENDING', 'APPROVED', 'REJECTED', 'RETURNED', 'CANCELLED'];

    public const UNITS = ['KG', 'PCS', 'YDS', 'MTR', 'DZN', 'SET', 'LBS', 'CONE'];

    public const CURRENCIES = ['USD', 'BDT', 'EUR', 'GBP'];

    protected $table = 'approval_tickets';

    protected $fillable = [
        'company_id', 'request_no', 'request_type_id', 'priority', 'title', 'reference_type', 'reference_no',
        'buyer_name', 'order_no', 'style_no', 'po_no', 'quantity', 'unit', 'amount', 'currency',
        'request_to_user_id', 'due_date', 'remarks', 'status', 'approval_status', 'approval_request_id',
        'requested_by', 'submitted_at',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'amount' => 'decimal:2', 'due_date' => 'date', 'submitted_at' => 'datetime'];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequestType::class, 'request_type_id');
    }

    public function requestTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'request_to_user_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** The current round's request in the engine, once submitted. */
    public function approvalRequest(): ?ApprovalRequest
    {
        return $this->approval_request_id ? ApprovalRequest::query()->find($this->approval_request_id) : null;
    }

    /** The title it is shown under: its own, or the type and reference. */
    public function displayTitle(): string
    {
        if ($this->title) {
            return $this->title;
        }
        $type = $this->relationLoaded('type') ? $this->type?->name : ApprovalRequestType::query()->whereKey($this->request_type_id)->value('name');

        return trim(($type ?? '').' · '.($this->reference_no ?? ''), ' ·');
    }

    public function approvalContext(): array
    {
        return [
            'cost' => number_format((float) ($this->amount ?? 0), 4, '.', ''),
            'currency' => $this->currency ?? 'BDT',
            'priority' => $this->priority,
        ];
    }

    public function approvalSummary(): array
    {
        return [
            'number' => (string) $this->request_no,
            'title' => $this->displayTitle(),
            'url' => rtrim((string) config('tenancy.frontend_url'), '/').'/approval-center/all-requests?open='.$this->id,
            'factory_id' => null,
        ];
    }

    public function onApprovalDecided(string $status): void
    {
        $next = match ($status) {
            'APPROVED' => 'APPROVED',
            'REJECTED' => 'REJECTED',
            'RETURNED' => 'RETURNED',
            'CANCELLED', 'NOT_REQUIRED' => 'CANCELLED',
            default => null,
        };
        if ($next !== null) {
            $this->forceFill(['status' => $next])->save();
        }
    }
}
