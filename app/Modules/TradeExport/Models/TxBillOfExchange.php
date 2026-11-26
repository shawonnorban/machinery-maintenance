<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A bill negotiated at the bank, or an advance against export. Trade & Export → Operations (AnnotechERP 22/02).
 */
class TxBillOfExchange extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'tx_bills_of_exchange';

    protected $fillable = ['company_id', 'bill_no', 'is_advance', 'lc_id', 'buyer_id', 'applicant_id', 'bill_date', 'bill_amount', 'currency', 'local_rate', 'local_currency', 'issuing_branch_id', 'advising_branch_id', 'doc_credit_branch_id', 'receiving_branch_id', 'same_receiver_bank', 'payment_term', 'remarks', 'bank_bill_type', 'bank_bill_ref_no', 'remittance_ref_no', 'due_date', 'ref_date', 'link_type', 'pi_id', 'order_id', 'margin_type', 'bank_submit_date', 'payment_recv_date', 'final', 'created_by'];

    protected function casts(): array
    {
        return ['bill_date' => 'date', 'due_date' => 'date', 'ref_date' => 'date', 'bank_submit_date' => 'date', 'payment_recv_date' => 'date', 'is_advance' => 'boolean', 'same_receiver_bank' => 'boolean', 'final' => 'boolean', 'bill_amount' => 'float', 'local_rate' => 'float'];
    }
}
