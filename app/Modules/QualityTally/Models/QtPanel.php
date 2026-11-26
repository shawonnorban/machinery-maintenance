<?php

declare(strict_types=1);

namespace App\Modules\QualityTally\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A panel of a garment type (T-Shirt: Back, Front, Sleeve). Quality Tally (AnnotechERP 36).
 */
class QtPanel extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'qt_panels';

    protected $fillable = ['company_id', 'garment_type', 'code', 'name', 'per_piece', 'sort_order', 'active'];

    protected function casts(): array
    {
        return ['per_piece' => 'integer', 'sort_order' => 'integer', 'active' => 'boolean'];
    }
}
