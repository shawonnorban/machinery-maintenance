<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A department answerable for IE activities (Responsible Owner Mapping). Work Study → Master Data (AnnotechERP 14/01).
 */
class WsResponsibleDept extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ws_responsible_depts';

    protected $fillable = ['company_id', 'name', 'code', 'sl'];

    protected function casts(): array
    {
        return ['sl' => 'integer'];
    }
}
