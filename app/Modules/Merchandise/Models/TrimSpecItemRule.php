<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Where a spec template applies: an item category, item and buyer. Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class TrimSpecItemRule extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'trim_spec_item_rules';

    protected $fillable = ['company_id', 'trim_spec_template_id', 'item_category', 'item_name', 'buyer_id'];
}
