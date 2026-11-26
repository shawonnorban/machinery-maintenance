<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A fiber blend whose parts total 100%, described from them: "60% Cotton 40% Polyester" (Fiber Composition). Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class FiberComposition extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'fiber_compositions';

    protected $fillable = ['company_id', 'code', 'description', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
