<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One thing a batch card is made of — grey fabric by the kilo and roll, collar & cuff by the piece, trims by the yard, or finished fabric. Dye House → Batch (Bulk) (AnnotechERP 17/03).
 */
class DyeBatchItem extends BaseModel
{
    use BelongsToTenant;

    public const KINDS = ['GREY', 'COLLAR_CUFF', 'TRIMS', 'FINISHED'];

    protected $table = 'dye_batch_items';

    protected $fillable = ['company_id', 'batch_id', 'kind', 'description', 'qty_kg', 'issue_kg', 'rolls', 'issue_rolls', 'pcs', 'issue_pcs', 'yds', 'sort_order'];

    protected function casts(): array
    {
        return ['qty_kg' => 'decimal:3', 'issue_kg' => 'decimal:3', 'yds' => 'decimal:2', 'rolls' => 'integer', 'issue_rolls' => 'integer', 'pcs' => 'integer', 'issue_pcs' => 'integer'];
    }
}
