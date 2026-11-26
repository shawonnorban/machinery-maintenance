<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A purchased fabric receipt (MRR) against a fabric purchase order (Trims & Accessories, AnnotechERP 06/06). */
class PurchasedFabricMrr extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_pfab_mrrs';

    protected $fillable = [
        'company_id', 'mrr_no', 'unit_company_id', 'supplier_id', 'store_id', 'po_id', 'mrr_date', 'payment_method', 'source_type', 'challan_no', 'challan_date',
        'currency', 'conv_rate', 'lc_no', 'lc_date', 'bill_entry_no', 'bill_entry_date', 'remarks', 'discount_pct', 'discount_amount', 'vat_pct', 'tds_pct', 'vds_pct',
        'goods_value', 'other_cost', 'landed_cost', 'vat_base', 'vat', 'tds', 'vds', 'net_amount', 'net_payable', 'voucher_no', 'status', 'finalized_at', 'finalized_by', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'mrr_date' => 'date', 'challan_date' => 'date', 'lc_date' => 'date', 'bill_entry_date' => 'date', 'finalized_at' => 'datetime', 'conv_rate' => 'float',
            'discount_pct' => 'float', 'discount_amount' => 'float', 'vat_pct' => 'float', 'tds_pct' => 'float', 'vds_pct' => 'float', 'goods_value' => 'float',
            'other_cost' => 'float', 'landed_cost' => 'float', 'vat_base' => 'float', 'vat' => 'float', 'tds' => 'float', 'vds' => 'float', 'net_amount' => 'float', 'net_payable' => 'float',
        ];
    }

    /** A finalised receipt is locked: its fabric is on stock. */
    public function isEditable(): bool
    {
        return $this->status === 'DRAFT';
    }
}
