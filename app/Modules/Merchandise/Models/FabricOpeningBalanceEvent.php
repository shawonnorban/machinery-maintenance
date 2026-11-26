<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** One workflow step an opening balance took: who moved it, from what to what, and when (Trims & Accessories, AnnotechERP 06/06). */
class FabricOpeningBalanceEvent extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_pfab_ob_events';

    protected $fillable = ['company_id', 'ob_id', 'action', 'from_status', 'to_status', 'user_id', 'remarks', 'acted_at'];

    protected function casts(): array
    {
        return ['acted_at' => 'datetime'];
    }
}
