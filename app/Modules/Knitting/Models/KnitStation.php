<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * An operator's station on the knitting floor.
 */
class KnitStation extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_stations';

    protected $fillable = ['company_id', 'code', 'name', 'operator_id', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
