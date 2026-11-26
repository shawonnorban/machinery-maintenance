<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Which finishing floors a user may post against, for one node (Finishing Floor Access Setup). Work Study → Master Data (AnnotechERP 14/01).
 */
class WsFinishingAccess extends BaseModel
{
    use BelongsToTenant;

    public const NODES = ['IRON', 'POLY'];

    protected $table = 'ws_finishing_access';

    protected $fillable = ['company_id', 'user_id', 'node_type', 'floor_ids'];

    protected function casts(): array
    {
        return ['floor_ids' => 'array'];
    }
}
