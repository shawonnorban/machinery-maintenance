<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** How much of one requisition line an issue releases. */
class MaterialIssueLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'proto_material_issue_lines';

    protected $fillable = ['company_id', 'issue_id', 'line_id', 'qty', 'remarks', 'sort_order'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:4', 'sort_order' => 'integer'];
    }
}
