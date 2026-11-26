<?php

declare(strict_types=1);

namespace App\Modules\OrderSummary\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A merchandising team: its department and team head. Order Summary (AnnotechERP 32).
 */
class OsTeam extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'os_teams';

    protected $fillable = ['company_id', 'name', 'department_id', 'team_head_id', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
