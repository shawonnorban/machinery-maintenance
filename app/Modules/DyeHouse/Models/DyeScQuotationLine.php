<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One rate head of a subcontract quotation. Dye House → Master Data (AnnotechERP 17/01).
 */
class DyeScQuotationLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_sc_quotation_lines';

    protected $fillable = ['company_id', 'quotation_id', 'rate_line_id', 'head', 'uom', 'rate', 'sort_order'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:4', 'sort_order' => 'integer'];
    }
}
