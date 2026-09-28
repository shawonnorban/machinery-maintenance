<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Models;

use App\Modules\Approval\Contracts\Approvable;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Vendor\Models\Vendor;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sending goods back to the supplier (Procurement spec §12).
 *
 * Quality failed, the wrong thing arrived, or more came than was ordered.
 * The order of the flow is deliberate: approval first, then the gate pass,
 * then the stock comes down, then the debit note. Stock that left the
 * building before anybody approved it is stock nobody can account for, and
 * a debit note raised before the goods moved is a claim against a supplier
 * who still has nothing back.
 *
 * The debit note number is the document the supplier argues with, so it is
 * allocated at dispatch — when the goods actually leave — rather than at
 * draft, where a cancelled return would leave a gap in the series somebody
 * has to explain.
 */
class PurchaseReturn extends BaseModel implements Approvable
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'PENDING_APPROVAL', 'APPROVED', 'DISPATCHED', 'REJECTED', 'CANCELLED'];

    /** Why the goods are going back (§12). */
    public const REASONS = ['QUALITY_FAIL', 'EXCESS', 'WRONG_ITEM', 'DAMAGE', 'OTHER'];

    /**
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        'DRAFT' => ['PENDING_APPROVAL', 'APPROVED', 'CANCELLED'],
        'PENDING_APPROVAL' => ['APPROVED', 'REJECTED', 'CANCELLED'],
        // Approved but still on the shelf until the gate pass is raised.
        'APPROVED' => ['DISPATCHED', 'CANCELLED'],
        'DISPATCHED' => [],
        'REJECTED' => [],
        'CANCELLED' => [],
    ];

    protected $table = 'purchase_returns';

    protected $fillable = [
        'company_id', 'factory_id', 'return_no', 'return_date', 'vendor_id',
        'goods_receipt_id', 'reason', 'status', 'approval_status', 'approval_request_id',
        'gate_pass_no', 'debit_note_no', 'debit_note_amount', 'currency', 'notes',
        'created_by', 'dispatched_by', 'submitted_at', 'approved_at', 'dispatched_at',
        'rejected_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'return_date' => 'date',
            'debit_note_amount' => MoneyCast::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseReturnLine::class)->orderBy('created_at')->orderBy('id');
    }

    /** What is going back, at the price it came in at. */
    public function totalValue(): string
    {
        $this->loadMissing('lines');

        return $this->lines->reduce(
            fn (string $sum, PurchaseReturnLine $line): string => bcadd(
                $sum,
                bcmul((string) $line->quantity, (string) ($line->unit_cost ?? '0'), 4),
                4,
            ),
            '0.0000',
        );
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isEditable(): bool
    {
        return $this->status === 'DRAFT';
    }

    /** Approved, and the goods have not left yet. */
    public function isDispatchable(): bool
    {
        return $this->status === 'APPROVED';
    }

    /**
     * What the approver is deciding about: the value going back out of the
     * door, which is also what the debit note will be for.
     *
     * @return array<string, mixed>
     */
    public function approvalContext(): array
    {
        return [
            'cost' => (string) ($this->debit_note_amount ?? '0'),
            'currency' => $this->currency ?? 'BDT',
            'factory_id' => $this->factory_id,
            'reason' => $this->reason,
        ];
    }

    /**
     * @return array{number: string, title: string, url: string, factory_id: ?string}
     */
    public function approvalSummary(): array
    {
        return [
            'number' => $this->return_no,
            'title' => __('procurement.return_approval_title', ['number' => $this->return_no]),
            'url' => config('tenancy.frontend_url').'/procurement/returns/'.$this->id,
            'factory_id' => $this->factory_id,
        ];
    }

    public function onApprovalDecided(string $status): void
    {
        $now = CarbonImmutable::now();

        match ($status) {
            'APPROVED', 'NOT_REQUIRED' => in_array($this->status, ['DRAFT', 'PENDING_APPROVAL'], true)
                ? $this->forceFill(['status' => 'APPROVED', 'approved_at' => $now])->save()
                : null,
            'REJECTED' => $this->status === 'PENDING_APPROVAL'
                ? $this->forceFill(['status' => 'REJECTED', 'rejected_at' => $now])->save()
                : null,
            default => null,
        };
    }
}
