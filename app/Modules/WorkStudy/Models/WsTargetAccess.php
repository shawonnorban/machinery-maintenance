<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Which floors a user may set targets and products on, and with what right (Floor-Wise Target Setup). Work Study → Master Data (AnnotechERP 14/01).
 */
class WsTargetAccess extends BaseModel
{
    use BelongsToTenant;

    public const RIGHTS = ['R', 'W'];

    protected $table = 'ws_target_access';

    protected $fillable = ['company_id', 'user_id', 'floor_ids', 'target_right', 'product_right', 'is_admin'];

    protected function casts(): array
    {
        return ['floor_ids' => 'array', 'is_admin' => 'boolean'];
    }
}
