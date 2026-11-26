<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** An accessories receipt (MRR): what a supplier delivered to store, its other costs and its bill (Trims & Accessories, AnnotechERP 06/06). */
class AccReceipt extends BaseModel
{
    use BelongsToTenant;

    /** PO against an order, PI against a proforma, Exchangeable for goods coming back in. */
    public const TYPES = ['PO', 'PI', 'EXCHANGEABLE'];

    public const STATUSES = ['DRAFT', 'FINALIZED'];

    /** Brickland's store requisition types, as its Requisition Type list offers them. */
    public const REQUISITION_TYPES = [
        'Fin Fab Req by Sample Section', 'Grey Fab Req for Bulk Batch', 'Grey Fab Req for Sample Batch', 'Req for Dyed Yarn for S/C Knitting (Out-House)',
        'Req for Grey Fab Req for S/C Program', 'Req for Solid Yarn for S/C Knitting (Out-House) - Bulk', 'Requisition For Yarn Dye (Y/D)', 'Requisition for Loan Issue',
        'Requisition for Loan Return from Party', 'Requisition for Loan Return to Party', 'Requisition for Store Transfer', 'Store Requisition for Batch Dyeing',
        'Store Requisition for Finishing Process', 'Store Requisition for Knit M/C Oil', 'Store Requisition for Knitting - Bulk', 'Store Requisition for Knitting - Development',
        'Store Requisition for Knitting - Sample', 'Store Requisition for Loan Receive', 'Store Requisition for M/C Wash', 'Store Requisition for Purchase',
        'Store Requisition for Yarn Test', 'Yarn Requisition For Collar and/or Cuff (Bulk)',
    ];

    protected $table = 'merch_acc_mrrs';

    protected $fillable = [
        'company_id', 'mrr_no', 'mrr_type', 'unit_company_id', 'supplier_id', 'store_id', 'mrr_date', 'payment_method', 'source_type', 'challan_no', 'challan_date',
        'currency', 'conv_rate', 'po_id', 'requisition_id', 'requisition_type', 'remarks', 'discount_pct', 'discount_amount', 'vat_pct', 'tds_pct', 'vds_pct',
        'goods_value', 'other_cost', 'landed_cost', 'vat_base', 'vat', 'tds', 'vds', 'net_payable', 'voucher_no', 'status', 'finalized_at', 'finalized_by', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'mrr_date' => 'date', 'challan_date' => 'date', 'finalized_at' => 'datetime', 'conv_rate' => 'float', 'discount_pct' => 'float', 'discount_amount' => 'float',
            'vat_pct' => 'float', 'tds_pct' => 'float', 'vds_pct' => 'float', 'goods_value' => 'float', 'other_cost' => 'float', 'landed_cost' => 'float',
            'vat_base' => 'float', 'vat' => 'float', 'tds' => 'float', 'vds' => 'float', 'net_payable' => 'float',
        ];
    }

    /** A finalised receipt is locked: its goods are on stock. */
    public function isEditable(): bool
    {
        return $this->status === 'DRAFT';
    }
}
