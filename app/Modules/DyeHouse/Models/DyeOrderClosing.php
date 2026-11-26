<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Dyeing & Finishing Closing: an order closed out of dyeing and finishing for a scope (bulk or sample); its cards take no more production. Dye House → Production Board (AnnotechERP 17/07).
 */
class DyeOrderClosing extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_order_closings';

    protected $fillable = ['company_id', 'order_id', 'scope', 'remarks', 'closed_by'];

    protected function casts(): array
    {
        return [];
    }
}
