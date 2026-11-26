<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Models;

use App\Modules\Approval\Contracts\Approvable;
use App\Modules\Approval\Models\ApprovalAction;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A pre-budget: completed requisitions of one buyer, its number given on the
 * first save (PB-<buyer>-<MMYY>-<seq>), what was typed on its lines, the
 * Contract No, Discount/TPA % and the supplier price columns. Its lines are
 * worked out from its requisitions (BudgetSheet). Pre-Budget (AnnotechERP 33/34).
 *
 * Finalize sends it through the Approval Center (PRE_BUDGET: Merchandising
 * Head → CFO → MD): waiting it is PENDING and cannot change; approved it is
 * APPROVED and its requisitions are locked; a rejection sends it back
 * (RETURNED, to change and Finalize again); withdrawn it is a draft again.
 * An approved budget changes only by Revise: a copy numbered -R1, -R2 …
 * takes its place and it becomes REVISED (kept as it was approved).
 */
class PbBudget extends BaseModel implements Approvable
{
    use BelongsToTenant;
    use SoftDeletes;

    public const DRAFT = 'DRAFT';

    public const PENDING = 'PENDING';

    public const APPROVED = 'APPROVED';

    public const RETURNED = 'RETURNED';

    public const REVISED = 'REVISED';

    /** Cancelled by its order (Quantity, PAC or FOB changed after sending, or the order dropped): kept as it was; its revision budgets again. */
    public const CANCELLED = 'CANCELLED';

    /** Budgets that no longer hold their requisitions: replaced by a revision, or cancelled. */
    public const INACTIVE = [self::REVISED, self::CANCELLED];

    /** Budgets whose requisitions are locked (waiting for approval, or approved). */
    public const LOCKING = [self::PENDING, self::APPROVED];

    protected $table = 'pb_budgets';

    protected $fillable = ['company_id', 'budget_no', 'budget_seq', 'revision_no', 'parent_id', 'status', 'approval_status', 'approval_request_id', 'buyer_id', 'buyer_name', 'contract_no', 'discount_percent', 'dollar_rate', 'knit_note', 'dye_note', 'costing_done_at', 'costing_done_by', 'costing_basis', 'stock_done_at', 'stock_done_by', 'stock_basis', 'suppliers', 'version', 'submitted_at', 'submitted_by', 'approved_at', 'approved_by', 'revised_at', 'cancelled_at', 'cancelled_by', 'cancel_reason', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return [
            'budget_seq' => 'integer', 'revision_no' => 'integer', 'discount_percent' => 'float', 'dollar_rate' => 'float', 'costing_done_at' => 'datetime', 'stock_done_at' => 'datetime',
            'suppliers' => 'array', 'version' => 'integer', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'revised_at' => 'datetime', 'cancelled_at' => 'datetime',
        ];
    }

    /** The number as shown: PB-CNA-1126-0001, a revision with -R1. */
    public function label(): string
    {
        return $this->budget_no.($this->revision_no ? "-R{$this->revision_no}" : '');
    }

    /** Whether its lines and requisitions may still change (a draft, or one sent back). */
    public function editable(): bool
    {
        return in_array($this->status, [self::DRAFT, self::RETURNED], true);
    }

    public function requisitions(): BelongsToMany
    {
        return $this->belongsToMany(PbRequisition::class, 'pb_budget_requisitions', 'budget_id', 'requisition_id')->withTimestamps();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PbBudgetLine::class, 'budget_id');
    }

    public function costs(): HasMany
    {
        return $this->hasMany(PbBudgetCost::class, 'budget_id');
    }

    public function boms(): HasMany
    {
        return $this->hasMany(PbBom::class, 'budget_id')->orderBy('seq');
    }

    public function stockLots(): HasMany
    {
        return $this->hasMany(PbStockLot::class, 'budget_id')->orderBy('sort_order');
    }

    /* ── Approval Center (PRE_BUDGET) ───────────────────────────────── */

    /** The budget's total cost (USD) and buyer: what the approvers see as its amount. */
    public function approvalContext(): array
    {
        $view = (new \App\Modules\PreBudget\Support\BudgetSheet)->view($this);

        return [
            'cost' => number_format((float) $view['costs']['total'] + (float) $view['costs']['cm'], 4, '.', ''), 'currency' => 'USD',
            'buyer' => $this->buyer_name, 'net_fob' => $view['fob']['net'],
        ];
    }

    public function approvalSummary(): array
    {
        $styles = $this->requisitions()->get()->pluck('values.style')->filter()->unique()->count();

        return [
            'number' => $this->label(),
            'title' => trim(__('prebudget.final.approval_title', ['no' => $this->label(), 'buyer' => $this->buyer_name ?? '—', 'n' => $styles])),
            'url' => rtrim((string) config('tenancy.frontend_url'), '/').'/pre-budget/budget-finalized?budget='.rawurlencode($this->label()),
            'factory_id' => null,
        ];
    }

    public function onApprovalDecided(string $status): void
    {
        if ($this->status !== self::PENDING) {
            return;
        }

        match ($status) {
            'APPROVED', 'NOT_REQUIRED' => $this->forceFill(['status' => self::APPROVED, 'approved_at' => CarbonImmutable::now(), 'approved_by' => $this->lastApprover(), 'version' => $this->version + 1])->save(),
            // Rejected or returned: back with the merchandiser to change and Finalize again.
            'REJECTED', 'RETURNED' => $this->forceFill(['status' => self::RETURNED, 'version' => $this->version + 1])->save(),
            // Withdrawn by whoever sent it.
            'CANCELLED' => $this->forceFill(['status' => self::DRAFT, 'version' => $this->version + 1])->save(),
            default => null,
        };
    }

    private function lastApprover(): ?string
    {
        return $this->approval_request_id
            ? ApprovalAction::query()->where('approval_request_id', $this->approval_request_id)->where('action', 'APPROVED')->latest('acted_at')->value('approver_id')
            : null;
    }
}
