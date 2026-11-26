<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A purchased fabric purchase order against approved fabric indents (Trims & Accessories, AnnotechERP 06/06). */
class PurchasedFabricPo extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_pfab_pos';

    protected $fillable = [
        'company_id', 'po_no', 'po_date', 'supplier_id', 'source', 'indent_id', 'beneficiary_company_id', 'billed_to_company_id', 'delivery_store_id', 'is_online',
        'same_as_company', 'po_group', 'inco_term', 'place_of_delivery', 'payment_method', 'currency', 'conv_rate', 'payment_term', 'mode_of_shipment', 'delivery_date',
        'cash_supplier', 'other_term', 'remarks', 'discount_pct', 'discount_amount', 'vat_pct', 'tds_pct', 'vds_pct', 'adjustment', 'adjustment_note', 'po_value',
        'other_cost', 'vat', 'tds', 'vds', 'net_amount', 'revision', 'receive_status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'po_date' => 'date', 'delivery_date' => 'date', 'is_online' => 'boolean', 'same_as_company' => 'boolean', 'conv_rate' => 'float', 'discount_pct' => 'float',
            'discount_amount' => 'float', 'vat_pct' => 'float', 'tds_pct' => 'float', 'vds_pct' => 'float', 'adjustment' => 'float', 'po_value' => 'float',
            'other_cost' => 'float', 'vat' => 'float', 'tds' => 'float', 'vds' => 'float', 'net_amount' => 'float', 'revision' => 'integer',
        ];
    }

    /** An order received against is no longer changed here — it is revised instead. */
    public function isEditable(): bool
    {
        return $this->receive_status === 'PENDING';
    }
}
