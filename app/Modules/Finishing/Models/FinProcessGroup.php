<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A group processes are filed under — Knitting, Dyeing, Dyeing Finishing … Finishing Line → Master Data (AnnotechERP 18/01).
 */
class FinProcessGroup extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'fin_process_groups';

    protected $fillable = ['company_id', 'code', 'name', 'sort_order', 'active'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'active' => 'boolean'];
    }
}
