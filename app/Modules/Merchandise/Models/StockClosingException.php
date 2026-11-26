<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A document still open in a month being closed, as the last Month Close found it (Trims & Accessories, AnnotechERP 06/06). */
class StockClosingException extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_stock_closing_exceptions';

    protected $fillable = ['company_id', 'closing_id', 'document', 'doc_no', 'doc_date', 'status', 'sort_order'];

    protected function casts(): array
    {
        return ['doc_date' => 'date', 'sort_order' => 'integer'];
    }
}
