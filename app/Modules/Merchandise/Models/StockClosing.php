<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** One month of one stock closing kind: when it was last checked, how many documents stood in its way, and whether it closed (Trims & Accessories, AnnotechERP 06/06). */
class StockClosing extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_stock_closings';

    protected $fillable = ['company_id', 'kind', 'period', 'status', 'checked_at', 'exceptions', 'closed_at', 'closed_by', 'remarks'];

    protected function casts(): array
    {
        return ['checked_at' => 'datetime', 'closed_at' => 'datetime', 'exceptions' => 'integer'];
    }
}
