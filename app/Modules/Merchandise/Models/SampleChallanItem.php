<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** An item on a sample delivery challan: what went, of which sample type, how many (AnnotechERP 06/10). */
class SampleChallanItem extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_sample_challan_items';

    protected $fillable = ['company_id', 'challan_id', 'description', 'sample_type_id', 'qty', 'uom', 'sort_order'];

    protected function casts(): array
    {
        return ['qty' => 'float'];
    }
}
