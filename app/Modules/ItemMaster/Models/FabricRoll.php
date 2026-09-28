<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Bin;
use App\Modules\Procurement\Models\GoodsReceipt;
use App\Modules\Procurement\Models\GoodsReceiptLine;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One physical roll of fabric (Inventory spec §3.1) — the stock unit for
 * ROLL-tracked items. Where a QUANTITY/LOT item pools into a balance row,
 * a roll is its own record with its own measurements and history; this
 * table is the ledger for these items, not a supplement to one.
 */
class FabricRoll extends BaseModel
{
    use BelongsToTenant;

    /**
     * The full spec vocabulary (§3.2). Only RECEIVED/UNDER_INSPECTION/
     * AVAILABLE/DAMAGED/RETURNED are reachable by any action in Phase 2;
     * RESERVED/ISSUED/CLOSED arrive with Phase 3 (reservation/issue),
     * SPLIT/MERGED/SENT_FOR_SUBCONTRACT with Phase 4.
     */
    public const STATUSES = [
        'RECEIVED', 'UNDER_INSPECTION', 'AVAILABLE', 'DAMAGED', 'RETURNED',
        'RESERVED', 'ISSUED', 'CLOSED', 'SPLIT', 'MERGED', 'SENT_FOR_SUBCONTRACT',
    ];

    public const GRADES = ['A', 'B', 'C', 'REJECT'];

    /** What a REJECT grade resolves to — the roll's disposition. */
    public const DISPOSITIONS = ['DAMAGED', 'RETURNED'];

    /**
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        'UNDER_INSPECTION' => ['AVAILABLE', 'DAMAGED', 'RETURNED'],
        // A roll can be issued straight off the shelf; holding it first is
        // optional, which is why AVAILABLE reaches ISSUED directly.
        'AVAILABLE' => ['RESERVED', 'ISSUED', 'SPLIT', 'MERGED', 'SENT_FOR_SUBCONTRACT'],
        // A held roll may still be cut down — the claim follows the pieces
        // (see `SplitFabricRoll`) rather than blocking the knife.
        'RESERVED' => ['AVAILABLE', 'ISSUED', 'SPLIT'],
        'ISSUED' => ['AVAILABLE'],
        'SENT_FOR_SUBCONTRACT' => ['AVAILABLE', 'DAMAGED'],
        // Terminal: the roll's length now lives in its children, or in the
        // roll it was merged into.
        'SPLIT' => [],
        'MERGED' => [],
        'DAMAGED' => [],
        'RETURNED' => [],
    ];

    /** No longer a physical roll on the shelf. */
    public const CLOSED_STATUSES = ['SPLIT', 'MERGED', 'CLOSED'];

    protected $table = 'fabric_rolls';

    protected $fillable = [
        'company_id', 'item_id', 'goods_receipt_id', 'goods_receipt_line_id', 'bin_id',
        'roll_no', 'lot_no', 'shade', 'shade_band', 'nominal_width', 'actual_width',
        'gross_length', 'net_length', 'gross_weight', 'net_weight', 'status',
        'parent_roll_id', 'merged_into_roll_id', 'reserved_order_id', 'unit_cost', 'currency',
        'cost_adjusted_after_issue', 'inspection_grade', 'inspection_points', 'shade_match',
        'inspection_notes', 'inspected_by', 'inspected_at', 'received_date',
        'fabric_inspection_id', 'width_start', 'width_middle', 'width_end',
        'marked_length', 'inspected_length', 'shade_assessment', 'penalty_percent',
    ];

    protected function casts(): array
    {
        return [
            // Measurements are decimal(·,2) columns — Laravel's own
            // fixed-scale cast, the same one ItemFabricAttributes uses for
            // GSM/width. MoneyCast is reserved for genuine DECIMAL(18,4)
            // money columns (unit_cost here).
            'nominal_width' => 'decimal:2',
            'actual_width' => 'decimal:2',
            'gross_length' => 'decimal:2',
            'net_length' => 'decimal:2',
            'gross_weight' => 'decimal:2',
            'net_weight' => 'decimal:2',
            'inspection_points' => 'decimal:2',
            'width_start' => 'decimal:2',
            'width_middle' => 'decimal:2',
            'width_end' => 'decimal:2',
            'marked_length' => 'decimal:2',
            'inspected_length' => 'decimal:2',
            'penalty_percent' => 'decimal:2',
            'shade_assessment' => 'array',
            'unit_cost' => MoneyCast::class,
            'cost_adjusted_after_issue' => 'boolean',
            'shade_match' => 'boolean',
            'inspected_at' => 'datetime',
            'received_date' => 'date',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function goodsReceiptLine(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptLine::class, 'goods_receipt_line_id');
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    /**
     * Shortfall against the supplier's own ticket.
     *
     * Null when only one of the two numbers was written down — a missing
     * measurement is not the same as a roll that measured exactly right,
     * and showing 0.00 for it would quietly absolve a short delivery.
     */
    public function lengthShortfall(): ?string
    {
        if ($this->marked_length === null || $this->inspected_length === null) {
            return null;
        }

        return bcsub((string) $this->marked_length, (string) $this->inspected_length, 2);
    }

    /** Did every shade check on this roll come back clean? */
    public function shadeIsClean(): bool
    {
        $assessment = $this->shade_assessment ?? [];

        foreach ($assessment as $result) {
            if ($result === false || $result === 'FAIL') {
                return false;
            }
        }

        return true;
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(FabricInspection::class, 'fabric_inspection_id');
    }

    public function defectTally(): HasMany
    {
        return $this->hasMany(FabricRollDefect::class, 'fabric_roll_id');
    }

    public function defects(): BelongsToMany
    {
        return $this->belongsToMany(FabricDefect::class, 'fabric_roll_defects', 'fabric_roll_id', 'fabric_defect_id')
            ->withPivot('notes')
            ->withTimestamps();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_roll_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_roll_id');
    }

    /** The roll this one was merged into, if it was. */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_roll_id');
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /**
     * Which measurement this roll is counted in. Fabric is bought by the
     * metre or by the kilo depending on the construction, and a split has to
     * add up in whichever one the roll actually carries.
     */
    public function measure(): string
    {
        return $this->net_length !== null ? 'net_length' : 'net_weight';
    }

    public function measuredQuantity(): string
    {
        return (string) ($this->{$this->measure()} ?? '0');
    }

    /** What the whole roll is worth at its current unit cost. */
    public function totalValue(): string
    {
        return bcmul($this->measuredQuantity(), (string) $this->unit_cost, 4);
    }

    public function isOnShelf(): bool
    {
        return in_array($this->status, ['AVAILABLE', 'RESERVED'], true);
    }

    public function isPendingInspection(): bool
    {
        return $this->status === 'UNDER_INSPECTION';
    }

    /**
     * Which defect list this roll is inspected against. Woven stays its own
     * family; Knit, Fleece and Rib share the Knit list (spec only splits
     * defects two ways, and Fleece/Rib are knit-family constructions).
     */
    public function defectFamily(): string
    {
        $fabricType = $this->item?->fabricAttributes?->fabric_type;

        return $fabricType === 'WOVEN' ? 'WOVEN' : 'KNIT';
    }
}
