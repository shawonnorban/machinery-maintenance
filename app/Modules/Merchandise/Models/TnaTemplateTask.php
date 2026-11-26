<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A milestone on a template calendar, counted back from the shipment date. Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class TnaTemplateTask extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'tna_template_tasks';

    protected $fillable = ['company_id', 'tna_template_id', 'tna_task_id', 'offset_days', 'duration_days', 'sort_order', 'active'];

    protected function casts(): array
    {
        return ['offset_days' => 'integer', 'duration_days' => 'integer', 'sort_order' => 'integer', 'active' => 'boolean'];
    }
}
