<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A cheque book held against a bank account, with its leaf range (Cheque Book Register). Finance & Ledger → Master Data (AnnotechERP 23/01).
 */
class AccChequeBook extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'acc_cheque_books';

    protected $fillable = ['company_id', 'bank_account_id', 'book_no', 'required_leaf', 'prefix', 'no_from', 'no_to', 'requisition_date', 'est_receive_date', 'receive_date', 'remarks', 'finalized'];

    protected function casts(): array
    {
        return ['required_leaf' => 'integer', 'no_from' => 'integer', 'no_to' => 'integer', 'requisition_date' => 'date', 'est_receive_date' => 'date', 'receive_date' => 'date', 'finalized' => 'boolean'];
    }
}
