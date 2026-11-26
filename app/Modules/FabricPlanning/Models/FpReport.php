<?php

declare(strict_types=1);

namespace App\Modules\FabricPlanning\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A report of the module's catalogue (Batch Plan Register, Machine Load vs Capacity …). Fabric Requirement → Reports (AnnotechERP 13/03).
 */
class FpReport extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'fp_reports';

    protected $fillable = ['company_id', 'code', 'name', 'category', 'sort_order', 'active'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'active' => 'boolean'];
    }
}
