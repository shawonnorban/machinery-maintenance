<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Models;

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
 * One physical count of a store, or a slice of one (Inventory spec §12).
 *
 * A count starts in `COUNTING` rather than in a draft: the sheet **is** the
 * document, and the moment it is generated is the moment the system
 * quantities are frozen. There is nothing meaningful to hold in a draft
 * before that, and pretending otherwise would put a gap between the freeze
 * and the walk down the aisle — which is exactly the gap that turns
 * ordinary movements into invented variances.
 *
 * Posting is deliberately behind an approval: a count adjustment is the
 * one routine way stock value changes without anything arriving or
 * leaving, so it is also the easiest place to bury a loss.
 */
class StockCount extends BaseModel implements Approvable
{
    use BelongsToTenant;

    public const TYPES = ['CYCLE', 'FULL'];

    public const STATUSES = [
        'COUNTING', 'UNDER_REVIEW', 'PENDING_APPROVAL', 'APPROVED', 'POSTED', 'REJECTED', 'CANCELLED',
    ];

    /**
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        'COUNTING' => ['UNDER_REVIEW', 'CANCELLED'],
        // Straight to APPROVED when no workflow matches — the engine's own
        // NOT_REQUIRED verdict.
        'UNDER_REVIEW' => ['PENDING_APPROVAL', 'APPROVED', 'CANCELLED'],
        'PENDING_APPROVAL' => ['APPROVED', 'REJECTED', 'CANCELLED'],
        'APPROVED' => ['POSTED', 'CANCELLED'],
        // Terminal: the adjustments are in the ledger and cannot be unmade
        // by re-opening the sheet.
        'POSTED' => [],
        'REJECTED' => [],
        'CANCELLED' => [],
    ];

    protected $table = 'stock_counts';

    protected $fillable = [
        'company_id', 'factory_id', 'store_id', 'count_no', 'count_type', 'status',
        'is_blind', 'count_date', 'scope_json', 'variance_value', 'approval_status',
        'approval_request_id', 'notes', 'created_by', 'submitted_at', 'approved_at',
        'rejected_at', 'posted_by', 'posted_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'is_blind' => 'boolean',
            'count_date' => 'date',
            'scope_json' => 'array',
            'variance_value' => MoneyCast::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'posted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockCountLine::class);
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /** Still being walked. */
    public function isCounting(): bool
    {
        return $this->status === 'COUNTING';
    }

    /**
     * Whether the system quantity may be shown yet.
     *
     * On a blind count it is withheld while the counting is going on, and
     * only then: once the sheet is in for review the variance is the whole
     * subject of the conversation.
     */
    public function revealsSystemQty(): bool
    {
        return ! $this->is_blind || ! $this->isCounting();
    }

    public function isPostable(): bool
    {
        return $this->status === 'APPROVED';
    }

    /**
     * @return array<string, mixed>
     */
    public function approvalContext(): array
    {
        return [
            // The absolute value at stake, not the net: a store that is
            // 50,000 short of one item and 50,000 over on another has two
            // problems, not none.
            'cost' => (string) ($this->variance_value ?? '0'),
            'currency' => 'BDT',
            'factory_id' => $this->factory_id,
            'count_type' => $this->count_type,
        ];
    }

    /**
     * @return array{number: string, title: string, url: string, factory_id: ?string}
     */
    public function approvalSummary(): array
    {
        return [
            'number' => $this->count_no,
            'title' => __('itemmaster.count_approval_title', ['number' => $this->count_no]),
            'url' => config('tenancy.frontend_url').'/item-master/stock-counts/'.$this->id,
            'factory_id' => $this->factory_id,
        ];
    }

    public function onApprovalDecided(string $status): void
    {
        $now = CarbonImmutable::now();

        match ($status) {
            'APPROVED', 'NOT_REQUIRED' => in_array($this->status, ['UNDER_REVIEW', 'PENDING_APPROVAL'], true)
                ? $this->forceFill(['status' => 'APPROVED', 'approved_at' => $now])->save()
                : null,
            'REJECTED' => $this->status === 'PENDING_APPROVAL'
                ? $this->forceFill(['status' => 'REJECTED', 'rejected_at' => $now])->save()
                : null,
            default => null,
        };
    }
}
