<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** One milestone of an order's T&A calendar (T&A Followup, AnnotechERP 06/12). */
class OrderTna extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_order_tna';

    protected $fillable = ['company_id', 'order_id', 'tna_task_id', 'offset_days', 'plan_date', 'actual_date', 'required', 'auto', 'remarks', 'sort_order', 'updated_by'];

    protected function casts(): array
    {
        return ['plan_date' => 'date', 'actual_date' => 'date', 'required' => 'boolean', 'auto' => 'boolean', 'offset_days' => 'integer', 'sort_order' => 'integer'];
    }
}
