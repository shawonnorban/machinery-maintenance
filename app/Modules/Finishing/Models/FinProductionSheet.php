<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A shift's machine-wise finishing production on one line (Open or Tube): a draft until posted from the register, which moves each batch on. Finishing Line → DF Production (AnnotechERP 18/02).
 */
class FinProductionSheet extends BaseModel
{
    use BelongsToTenant;

    public const LINES = ['OPEN', 'TUBE'];

    public const SHIFTS = ['DAY', 'NIGHT'];

    protected $table = 'fin_production_sheets';

    protected $fillable = ['company_id', 'sheet_no', 'line', 'production_date', 'floor_id', 'shift', 'remarks', 'status', 'posted_at', 'posted_by', 'created_by'];

    protected function casts(): array
    {
        return ['production_date' => 'date', 'posted_at' => 'datetime'];
    }

    public function lines(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FinProductionLine::class, 'sheet_id')->orderBy('sort_order');
    }
}
