<?php

declare(strict_types=1);

namespace App\Modules\CommandCenter\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A dashboard assembled from the KPI library. Command Center → Dashboard Composer (AnnotechERP 27/01).
 */
class Composition extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'cc_compositions';

    protected $fillable = ['company_id', 'title', 'layout', 'author_id', 'tiles', 'charts', 'shared_with', 'state', 'notes'];

    protected function casts(): array
    {
        return ['tiles' => 'integer', 'charts' => 'integer'];
    }
}
