<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A shift team a bonus rule may be narrowed to (Bonus Rule). HR & Payroll → Master Data (AnnotechERP 24/01).
 */
class HrShiftTeam extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'hr_shift_teams';

    protected $fillable = ['company_id', 'name', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
