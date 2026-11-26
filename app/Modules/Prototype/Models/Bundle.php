<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** One bundle of a card: its scan code (SBC-2026100001-01), what the order asks and what came off the table, and where it was last scanned. */
class Bundle extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'proto_bundles';

    protected $fillable = ['company_id', 'card_id', 'bundle_no', 'order_id', 'sample_type_id', 'color', 'size', 'req_qty', 'cutting_qty', 'now_at', 'remarks', 'sort_order'];

    protected function casts(): array
    {
        return ['req_qty' => 'integer', 'cutting_qty' => 'integer', 'sort_order' => 'integer'];
    }
}
