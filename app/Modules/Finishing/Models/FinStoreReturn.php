<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Fabric returned from cutting into a lot, or transferred from a lot to another unit, the balance moved with it. Finishing Line → Fabric Store (AnnotechERP 18/05).
 */
class FinStoreReturn extends BaseModel
{
    use BelongsToTenant;

    public const KINDS = ['RETURN', 'TRANSFER'];

    protected $table = 'fin_store_returns';

    protected $fillable = ['company_id', 'return_no', 'return_date', 'kind', 'lot_id', 'requisition_id', 'qty_kg', 'qty_m', 'rolls', 'to_unit', 'reason', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['return_date' => 'date', 'qty_kg' => 'decimal:3', 'rolls' => 'integer'];
    }
}
