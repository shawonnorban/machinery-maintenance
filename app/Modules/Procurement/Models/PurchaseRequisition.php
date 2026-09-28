<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Models;

use App\Modules\Approval\Contracts\Approvable;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A request to buy (Procurement spec §5).
 *
 * Drafted, submitted, approved, then converted into purchase orders line by
 * line. It never touches stock. The approval engine writes `approval_status`;
 * the document moves its own `status` in `onApprovalDecided()`.
 */
class PurchaseRequisition extends BaseModel implements Approvable
{
    use BelongsToTenant;

    public const TYPES = ['MANUAL', 'AUTO', 'EMERGENCY', 'FROM_FLOOR_REQUISITION'];

    public const PRIORITIES = ['LOW', 'NORMAL', 'HIGH', 'URGENT'];

    public const STATUSES = [
        'DRAFT', 'PENDING_APPROVAL', 'APPROVED', 'REJECTED',
        'PARTIALLY_CONVERTED', 'FULLY_CONVERTED', 'CANCELLED',
    ];

    /**
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        'DRAFT' => ['PENDING_APPROVAL', 'APPROVED', 'CANCELLED'],
        'PENDING_APPROVAL' => ['APPROVED', 'REJECTED', 'CANCELLED'],
        'APPROVED' => ['PARTIALLY_CONVERTED', 'FULLY_CONVERTED', 'CANCELLED'],
        'REJECTED' => [],
        'PARTIALLY_CONVERTED' => ['FULLY_CONVERTED', 'CANCELLED'],
        'FULLY_CONVERTED' => [],
        'CANCELLED' => [],
    ];

    /** Statuses a purchase order may still be raised from. */
    public const CONVERTIBLE = ['APPROVED', 'PARTIALLY_CONVERTED'];

    protected $table = 'purchase_requisitions';

    protected $fillable = [
        'company_id', 'factory_id', 'store_id', 'pr_no', 'pr_date', 'pr_type', 'source_reference',
        'priority', 'status', 'approval_status', 'approval_request_id', 'requested_by',
        'submitted_at', 'approved_at', 'rejected_at', 'rejection_reason', 'cancelled_at',
        'estimated_total', 'currency', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'pr_date' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'estimated_total' => MoneyCast::class,
        ];
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseRequisitionLine::class)->orderBy('created_at')->orderBy('id');
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isTerminal(): bool
    {
        return self::TRANSITIONS[$this->status] === [];
    }

    public function isEditable(): bool
    {
        return $this->status === 'DRAFT';
    }

    public function isConvertible(): bool
    {
        return in_array($this->status, self::CONVERTIBLE, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function approvalContext(): array
    {
        return [
            'cost' => number_format((float) $this->estimated_total, 4, '.', ''),
            'currency' => $this->currency ?? 'BDT',
            'factory_id' => $this->factory_id,
            'priority' => $this->priority,
            'pr_type' => $this->pr_type,
        ];
    }

    /**
     * @return array{number: string, title: string, url: string, factory_id: ?string}
     */
    public function approvalSummary(): array
    {
        return [
            'number' => $this->pr_no,
            'title' => __('procurement.pr_approval_title', ['number' => $this->pr_no]),
            'url' => config('tenancy.frontend_url').'/procurement/requisitions/'.$this->id,
            'factory_id' => $this->factory_id,
        ];
    }

    public function onApprovalDecided(string $status): void
    {
        $now = CarbonImmutable::now();

        match ($status) {
            'APPROVED', 'NOT_REQUIRED' => $this->status === 'PENDING_APPROVAL' || $this->status === 'DRAFT'
                ? $this->forceFill(['status' => 'APPROVED', 'approved_at' => $now])->save()
                : null,
            'REJECTED' => $this->status === 'PENDING_APPROVAL'
                ? $this->forceFill(['status' => 'REJECTED', 'rejected_at' => $now])->save()
                : null,
            default => null,
        };
    }
}
