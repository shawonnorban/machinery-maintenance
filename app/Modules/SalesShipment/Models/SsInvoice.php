<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A shipment invoice (INV-…): consignee, notify party, shipping and terms, and the shipment lines it bills; drafted, finalized or cancelled. Sales & Shipment → Shipment (AnnotechERP 21/05).
 */
class SsInvoice extends BaseModel
{
    use BelongsToTenant;

    public const STATES = ['DRAFT', 'FINAL', 'CANCELLED'];

    protected $table = 'ss_invoices';

    protected $fillable = ['company_id', 'invoice_no', 'shipment_id', 'invoice_date', 'currency_id', 'commercial_invoice_ref', 'consignee', 'notify_party', 'country_id', 'port', 'shipment_mode', 'incoterm', 'payment_terms', 'lc_contract_no', 'remarks', 'state', 'created_by'];

    protected function casts(): array
    {
        return ['invoice_date' => 'date'];
    }
}
