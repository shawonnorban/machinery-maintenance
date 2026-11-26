<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A line's manpower and machines before it is loaded, by machine type or overall (Resource Capability Profile). Work Study → Master Data (AnnotechERP 14/01).
 */
class WsLineProfile extends BaseModel
{
    use BelongsToTenant;

    public const MACHINE_TYPES = ['SNLS', 'OVERLOCK', 'FLATLOCK', 'BUTTON_ATTACH', 'BUTTON_HOLE', 'BARTACK'];

    protected $table = 'ws_line_profiles';

    protected $fillable = ['company_id', 'planning_line_id', 'machine_type', 'operators', 'aso', 'machines'];

    protected function casts(): array
    {
        return ['operators' => 'integer', 'aso' => 'integer', 'machines' => 'integer'];
    }
}
