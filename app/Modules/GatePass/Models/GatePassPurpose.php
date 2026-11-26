<?php

declare(strict_types=1);

namespace App\Modules\GatePass\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Why goods or people go out — embroidery, repair, sample approval, buyer submission. Gate Pass → Master Data (AnnotechERP 11/01).
 */
class GatePassPurpose extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'gate_pass_purposes';

    protected $fillable = ['company_id', 'code', 'name', 'description', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
