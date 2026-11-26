<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A commercial invoice published to a buyer. Buyer Gateway → Invoice Lookup (AnnotechERP 25/03).
 */
class PortalInvoice extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'bg_invoices';

    protected $fillable = ['company_id', 'invoice_no', 'buyer_id', 'order_no', 'amount', 'issued_on', 'due_on', 'state'];

    protected function casts(): array
    {
        return ['amount' => 'float', 'issued_on' => 'date', 'due_on' => 'date'];
    }
}
