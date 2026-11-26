<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A group of T&A tasks — general process, sample, bulk production, shipment (Task & Action Register). Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class TnaTaskGroup extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'tna_task_groups';

    protected $fillable = ['company_id', 'code', 'name', 'name_bn', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
