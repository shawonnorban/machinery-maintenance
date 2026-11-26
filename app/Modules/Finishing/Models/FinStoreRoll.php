<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A roll of a store lot: where it is racked and the barcode it carries, so it can be found again. Finishing Line → Fabric Store (AnnotechERP 18/05).
 */
class FinStoreRoll extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['IN_STORE', 'ISSUED'];

    protected $table = 'fin_store_rolls';

    protected $fillable = ['company_id', 'lot_id', 'roll_no', 'barcode', 'weight_kg', 'length_m', 'rack', 'bin', 'status', 'remarks'];

    protected function casts(): array
    {
        return ['weight_kg' => 'decimal:3'];
    }
}
