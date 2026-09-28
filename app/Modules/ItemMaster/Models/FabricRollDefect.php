<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One defect observed on one roll (Inventory spec §4.3), with room for a note. */
class FabricRollDefect extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'fabric_roll_defects';

    protected $fillable = [
        'company_id', 'fabric_roll_id', 'fabric_defect_id', 'point_class', 'occurrences', 'notes',
    ];

    protected function casts(): array
    {
        return ['point_class' => 'integer', 'occurrences' => 'integer'];
    }

    /** What this row contributes to the roll's score. */
    public function points(): int
    {
        return $this->point_class * $this->occurrences;
    }

    public function roll(): BelongsTo
    {
        return $this->belongsTo(FabricRoll::class, 'fabric_roll_id');
    }

    public function defect(): BelongsTo
    {
        return $this->belongsTo(FabricDefect::class, 'fabric_defect_id');
    }
}
