<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * How much of its orders a buyer sees. Buyer Gateway → Order Visibility Rules (AnnotechERP 25/02).
 */
class VisibilityRule extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'bg_visibility_rules';

    protected $fillable = ['company_id', 'buyer_id', 'scope', 'depth', 'delays', 'effective_from', 'set_by', 'note', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'effective_from' => 'date'];
    }
}
