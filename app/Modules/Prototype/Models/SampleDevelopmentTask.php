<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** One of a development's fourteen checklist points. */
class SampleDevelopmentTask extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'proto_development_tasks';

    protected $fillable = ['company_id', 'development_id', 'task', 'status', 'completed_at', 'remarks', 'sort_order'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime', 'sort_order' => 'integer'];
    }
}
