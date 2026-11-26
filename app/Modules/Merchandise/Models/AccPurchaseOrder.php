<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A purchase order for trims and accessories against approved requisitions (Trims & Accessories, AnnotechERP 06/06). */
class AccPurchaseOrder extends BaseModel
{
    use BelongsToTenant;

    public const SOURCES = ['LOCAL', 'IMPORT'];

    public const INCO_TERMS = ['FOB', 'CFR', 'CIF', 'CPT', 'EXW', 'DDP'];

    public const PAYMENT_METHODS = ['CASH', 'LC', 'CHEQUE', 'TT', 'CONSIGNMENT'];

    public const CURRENCIES = ['BDT', 'USD', 'EUR', 'CNY', 'INR'];

    public const PAYMENT_TERMS = ['AT_SIGHT', 'LC_30', 'LC_60', 'LC_90', 'COD', 'ADVANCE', 'CREDIT_30'];

    public const SHIPMENT_MODES = ['ROAD', 'SEA', 'AIR', 'COURIER'];

    /** Nothing received yet, part of it, all of it — the receipt (MRR) moves it on. */
    public const RECEIVE_STATUSES = ['PENDING', 'PARTIAL', 'RECEIVED'];

    protected $table = 'merch_acc_pos';

    protected $fillable = [
        'company_id', 'po_no', 'po_date', 'supplier_id', 'source', 'requisition_id', 'beneficiary_company_id', 'billed_to_company_id', 'delivery_store_id',
        'is_online', 'same_as_company', 'inco_term', 'place_of_delivery', 'payment_method', 'currency', 'conv_rate', 'payment_term', 'mode_of_shipment',
        'delivery_date', 'cash_supplier', 'other_term', 'remarks', 'revision', 'receive_status', 'created_by',
    ];

    protected function casts(): array
    {
        return ['po_date' => 'date', 'delivery_date' => 'date', 'is_online' => 'boolean', 'same_as_company' => 'boolean', 'conv_rate' => 'float', 'revision' => 'integer'];
    }

    /** An order received against is no longer changed here — it is revised instead. */
    public function isEditable(): bool
    {
        return $this->receive_status === 'PENDING';
    }
}
