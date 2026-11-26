<?php

declare(strict_types=1);

namespace App\Modules\FabricPlanning\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A day a section of a unit does not run (blank section: the whole unit). Fabric Requirement → Factory Calendar & Holidays (AnnotechERP 13/02).
 */
class FpHoliday extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['WEEKLY_OFF', 'GOVT_HOLIDAY', 'COMPANY_HOLIDAY'];

    protected $table = 'fp_holidays';

    protected $fillable = ['company_id', 'section_id', 'factory_id', 'day', 'day_type', 'name'];

    protected function casts(): array
    {
        return ['day' => 'date'];
    }
}
