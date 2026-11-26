<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A fiber yarn is spun from — cotton, polyester, elastane … (Fiber Master). Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class Fiber extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'fibers';

    protected $fillable = ['company_id', 'code', 'name', 'short_name', 'sort_order', 'active'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'active' => 'boolean'];
    }
}
