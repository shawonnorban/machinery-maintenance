<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A buyer's request for quotation, before it becomes an inquiry. Buyer Gateway → Quotation Request Inbox (AnnotechERP 25/03).
 */
class Rfq extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'bg_rfqs';

    protected $fillable = ['company_id', 'reference', 'buyer_id', 'product_id', 'quantity', 'target_price', 'received_on', 'respond_by', 'state'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'target_price' => 'float', 'received_on' => 'date', 'respond_by' => 'date'];
    }
}
