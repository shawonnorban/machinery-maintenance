<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A sample program a material requisition covers. */
class MaterialRequisitionProgram extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'proto_mr_programs';

    protected $fillable = ['company_id', 'requisition_id', 'program_id', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }
}
