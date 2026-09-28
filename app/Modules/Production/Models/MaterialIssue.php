<?php

declare(strict_types=1);

namespace App\Modules\Production\Models;

use App\Modules\Approval\Contracts\Approvable;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\ItemMaster\Models\FabricRoll;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\Production\Actions\IssueToProduction;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One hand-over of material to a cutting programme, with the BOM variance
 * it produced (Inventory spec §9).
 *
 * An issue inside the factory's tolerance is final the moment it is made.
 * One that pushes the variance past it is held: the row is written
 * PENDING_APPROVAL and **no stock moves** until somebody signs it off. That
 * is the point of the tolerance — a store that hands over the extra fabric
 * first and asks afterwards has a report, not a control.
 */
class MaterialIssue extends BaseModel implements Approvable
{
    use BelongsToTenant;

    public const STATUSES = ['PENDING_APPROVAL', 'POSTED', 'REJECTED', 'CANCELLED'];

    protected $table = 'production_material_issues';

    protected $fillable = [
        'company_id', 'cutting_program_id', 'item_id', 'item_code', 'item_name', 'uom_id',
        'bin_id', 'lot_no', 'fabric_roll_id', 'quantity', 'unit_cost', 'currency',
        'item_stock_transaction_id', 'required_qty', 'issued_to_date', 'variance_qty',
        'variance_percent', 'is_extra', 'reason', 'status', 'approval_status',
        'approval_request_id', 'issued_by', 'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => MoneyCast::class,
            'unit_cost' => MoneyCast::class,
            'required_qty' => MoneyCast::class,
            'issued_to_date' => MoneyCast::class,
            'variance_qty' => MoneyCast::class,
            'variance_percent' => 'decimal:2',
            'is_extra' => 'boolean',
            'issued_at' => 'datetime',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(CuttingProgram::class, 'cutting_program_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class);
    }

    public function fabricRoll(): BelongsTo
    {
        return $this->belongsTo(FabricRoll::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'PENDING_APPROVAL';
    }

    /**
     * The value at stake is what the excess is worth, not the whole issue:
     * an approval threshold measured on the full quantity would demand a
     * director's signature for two metres over on a large cut.
     *
     * @return array<string, mixed>
     */
    public function approvalContext(): array
    {
        $excess = bccomp((string) $this->variance_qty, '0', 4) === 1 ? (string) $this->variance_qty : '0.0000';

        return [
            'cost' => bcmul($excess, (string) $this->unit_cost, 4),
            'currency' => $this->currency ?? 'BDT',
            'factory_id' => $this->program?->factory_id,
            'variance_percent' => (string) $this->variance_percent,
            'is_extra' => $this->is_extra,
        ];
    }

    /**
     * @return array{number: string, title: string, url: string, factory_id: ?string}
     */
    public function approvalSummary(): array
    {
        $program = $this->program;

        return [
            'number' => $program?->program_no ?? $this->id,
            'title' => __('production.variance_approval_title', [
                'item' => $this->item_name,
                'percent' => (string) $this->variance_percent,
                'number' => $program?->program_no ?? '',
            ]),
            'url' => config('tenancy.frontend_url').'/production/cutting-programs/'.$this->cutting_program_id,
            'factory_id' => $program?->factory_id,
        ];
    }

    /**
     * The stock moves here, not when the issue was drafted: an approval that
     * arrives after the fabric has already left the store is a formality.
     *
     * Resolving the action from the container is the one place this model
     * reaches for behaviour (ADR-003 keeps logic in Actions) — the engine
     * hands the verdict to the document, and the document has to be able to
     * act on it.
     */
    public function onApprovalDecided(string $status): void
    {
        match ($status) {
            'APPROVED', 'NOT_REQUIRED' => $this->isPending()
                ? app(IssueToProduction::class)->post($this)
                : null,
            'REJECTED' => $this->isPending()
                ? $this->forceFill(['status' => 'REJECTED'])->save()
                : null,
            default => null,
        };
    }
}
