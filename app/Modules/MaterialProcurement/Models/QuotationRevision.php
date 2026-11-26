<?php

declare(strict_types=1);

namespace App\Modules\MaterialProcurement\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One price a supplier sent on its quotation — quantity, unit price and
 * currency, lead time and expected delivery. Never edited or deleted once
 * submitted; the price review reads the latest.
 */
class QuotationRevision extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'mp_quotation_revisions';

    protected $fillable = ['company_id', 'quotation_id', 'revision', 'quoted_qty', 'unit_price', 'currency', 'lead_time_days', 'expected_delivery', 'remarks', 'submitted_by', 'submitted_at'];

    protected function casts(): array
    {
        return ['quoted_qty' => 'decimal:3', 'unit_price' => 'decimal:4', 'expected_delivery' => 'date', 'submitted_at' => 'datetime'];
    }
}
