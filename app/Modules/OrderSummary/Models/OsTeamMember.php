<?php

declare(strict_types=1);

namespace App\Modules\OrderSummary\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A merchandiser of a merchandising team. Order Summary (AnnotechERP 32).
 */
class OsTeamMember extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'os_team_members';

    protected $fillable = ['company_id', 'team_id', 'user_id'];

    protected function casts(): array
    {
        return [];
    }
}
