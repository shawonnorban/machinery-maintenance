<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A cost head's month: what it cost, its working days and how the money is re-arranged across the production columns. Work Study → Costing (AnnotechERP 14/05).
 */
class WsOhCost extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ws_oh_costs';

    protected $fillable = ['company_id', 'period', 'head_id', 'monthly_expense', 'work_days', 'cutting', 'sewing', 'knitting', 'dyeing', 'dyeing_finishing', 'printing'];

    protected function casts(): array
    {
        return ['monthly_expense' => 'decimal:2', 'work_days' => 'integer', 'cutting' => 'decimal:2', 'sewing' => 'decimal:2', 'knitting' => 'decimal:2', 'dyeing' => 'decimal:2', 'dyeing_finishing' => 'decimal:2', 'printing' => 'decimal:2'];
    }
}
