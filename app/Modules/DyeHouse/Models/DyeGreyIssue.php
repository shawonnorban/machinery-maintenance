<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * What the batch store issued against a requisition, off one grey stock line (Bulk Grey Fabric Issue Register). Dye House → Batch (Bulk) (AnnotechERP 17/03).
 */
class DyeGreyIssue extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_grey_issues';

    protected $fillable = ['company_id', 'indent_id', 'lot_id', 'issue_date', 'qty_kg', 'rolls', 'created_by'];

    protected function casts(): array
    {
        return ['issue_date' => 'date', 'qty_kg' => 'decimal:3', 'rolls' => 'integer'];
    }
}
