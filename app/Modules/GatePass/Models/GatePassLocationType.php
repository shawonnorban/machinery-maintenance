<?php

declare(strict_types=1);

namespace App\Modules\GatePass\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A kind of place goods go to — embroidery, printing, washing, a buyer office, a supplier. Gate Pass → Master Data (AnnotechERP 11/01).
 */
class GatePassLocationType extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'gate_pass_location_types';

    protected $fillable = ['company_id', 'code', 'name', 'description', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
