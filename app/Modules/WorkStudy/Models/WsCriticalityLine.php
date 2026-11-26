<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Whether a line runs a criticality band, and the garment category it is preferred for there (Resource Capability Profile). Work Study → Master Data (AnnotechERP 14/01).
 */
class WsCriticalityLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ws_criticality_lines';

    protected $fillable = ['company_id', 'criticality', 'planning_line_id', 'runs', 'preferred_group'];

    protected function casts(): array
    {
        return ['runs' => 'boolean'];
    }
}
