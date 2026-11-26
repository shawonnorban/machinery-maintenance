<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** Per company, the voucher type and ledger heads a finalised accessories receipt posts to (Trims & Accessories, AnnotechERP 06/06). */
class ProcPostingSetup extends BaseModel
{
    use BelongsToTenant;

    /** The ledger columns, debit then credit, as the receipt's voucher reads them. */
    public const LEDGERS = ['inventory_ledger_id', 'vat_ledger_id', 'payable_ledger_id', 'tds_ledger_id', 'vds_ledger_id'];

    protected $table = 'merch_proc_posting_setups';

    protected $fillable = ['company_id', 'unit_company_id', 'voucher_type', 'inventory_ledger_id', 'vat_ledger_id', 'payable_ledger_id', 'tds_ledger_id', 'vds_ledger_id', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
