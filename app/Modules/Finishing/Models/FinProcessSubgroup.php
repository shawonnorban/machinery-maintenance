<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A sub-group inside a process group — Wet, Dry or Surface Finishing. Finishing Line → Master Data (AnnotechERP 18/01).
 */
class FinProcessSubgroup extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'fin_process_subgroups';

    protected $fillable = ['company_id', 'group_id', 'code', 'name', 'sort_order', 'active'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'active' => 'boolean'];
    }
}
