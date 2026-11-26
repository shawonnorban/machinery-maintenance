<?php

declare(strict_types=1);

namespace App\Modules\GeneralStore\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One change to an item's stock in a store; the stock is the sum of these. General Store → Master Data (AnnotechERP 10/01).
 */
class GsStockMovement extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['OPENING', 'RECEIVE', 'ISSUE', 'RETURN', 'ADJUST', 'TRANSFER'];

    protected $table = 'gs_stock_movements';

    protected $fillable = ['company_id', 'gs_item_id', 'gs_store_id', 'gs_document_id', 'is_reversal', 'movement_type', 'quantity', 'unit_price', 'moved_on', 'reference', 'created_by'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'unit_price' => 'decimal:4', 'moved_on' => 'date', 'is_reversal' => 'boolean'];
    }
}
