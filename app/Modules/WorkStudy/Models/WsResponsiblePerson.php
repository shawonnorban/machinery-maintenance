<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A person who answers for a department, over which floors and which buyer accounts (Responsible Owner Mapping). Work Study → Master Data (AnnotechERP 14/01).
 */
class WsResponsiblePerson extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ws_responsible_persons';

    protected $fillable = ['company_id', 'dept_id', 'user_id', 'floor_ids', 'all_floors', 'buyer_ids', 'all_buyers'];

    protected function casts(): array
    {
        return ['floor_ids' => 'array', 'all_floors' => 'boolean', 'buyer_ids' => 'array', 'all_buyers' => 'boolean'];
    }
}
