<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A step on a budget sheet's approval route: approved or unlocked, with its note (AnnotechERP 06/09). */
class BudgetEvent extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_budget_events';

    protected $fillable = ['company_id', 'budget_id', 'action', 'note', 'user_id'];

    protected function casts(): array
    {
        return [];
    }
}
