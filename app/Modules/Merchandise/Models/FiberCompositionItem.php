<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One fiber and its share in a composition. Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class FiberCompositionItem extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'fiber_composition_items';

    protected $fillable = ['company_id', 'fiber_composition_id', 'fiber_id', 'percent'];

    protected function casts(): array
    {
        return ['percent' => 'decimal:2'];
    }
}
