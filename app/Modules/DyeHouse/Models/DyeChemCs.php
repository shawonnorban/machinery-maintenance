<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Chemical Comparative Statement (CS-): the suppliers' quoted rates laid side by side per item, and who each item is bought from. Dye House → Chemical Store (AnnotechERP 17/06).
 */
class DyeChemCs extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED', 'CLOSED'];

    protected $table = 'dye_chem_cs';

    protected $fillable = ['company_id', 'cs_no', 'cs_date', 'category_id', 'factory_id', 'mou_code', 'usd_rate', 'remarks', 'status', 'decision_note', 'decided_at', 'decided_by', 'created_by'];

    protected function casts(): array
    {
        return ['cs_date' => 'date', 'usd_rate' => 'float', 'decided_at' => 'datetime'];
    }
}
