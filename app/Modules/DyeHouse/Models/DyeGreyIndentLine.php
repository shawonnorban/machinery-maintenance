<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One line of a grey requisition, copied from its batch card. Dye House → Batch (Bulk) (AnnotechERP 17/03).
 */
class DyeGreyIndentLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_grey_indent_lines';

    protected $fillable = ['company_id', 'indent_id', 'batch_item_id', 'kind', 'description', 'req_kg', 'rolls', 'sort_order'];

    protected function casts(): array
    {
        return ['req_kg' => 'decimal:3', 'rolls' => 'integer'];
    }
}
