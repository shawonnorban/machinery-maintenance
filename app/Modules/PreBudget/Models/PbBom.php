<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A budget's accessories BOM: one or more of its styles, each with its own
 * accessory lines, numbered after the budget (PB-…-A1). A draft until it is
 * saved final; the budget's checklist wants every style in a final BOM.
 * Pre-Budget → Accessories Budget Pending (AnnotechERP 33/34).
 */
class PbBom extends BaseModel
{
    use BelongsToTenant;

    public const DRAFT = 'DRAFT';

    public const FINAL = 'FINAL';

    protected $table = 'pb_boms';

    protected $fillable = ['company_id', 'budget_id', 'seq', 'status', 'finalized_at', 'finalized_by', 'version', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['seq' => 'integer', 'version' => 'integer', 'finalized_at' => 'datetime'];
    }

    /** The number as shown: the budget's number with A1, A2 … */
    public function label(?PbBudget $budget = null): string
    {
        return ($budget ?? $this->budget)->label().'-A'.$this->seq;
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(PbBudget::class, 'budget_id');
    }

    public function styles(): HasMany
    {
        return $this->hasMany(PbBomStyle::class, 'bom_id')->orderBy('sort_order');
    }
}
