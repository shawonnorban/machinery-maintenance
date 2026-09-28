<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Which two accounts a business event moves (Shared Systems spec §2).
 *
 * Configuration rather than code: the spec's chart of accounts is not
 * signed off, so the mapping is data a finance team can change without a
 * deployment. Every event ships pointing at a placeholder account, which
 * is what lets the postings be correct in shape long before they are
 * correct in account.
 */
class GlEventMapping extends BaseModel
{
    use BelongsToTenant;

    /**
     * The events of spec §2, and what each one moves.
     *
     * @var array<string, array{debit: string, credit: string, secondary_credit?: string}>
     */
    public const EVENTS = [
        'GRN_COMPLETE' => ['debit' => 'STOCK', 'credit' => 'GRN_CLEARING'],
        'LANDED_COST_POST' => ['debit' => 'STOCK', 'credit' => 'LANDED_COST_PAYABLE'],
        'ISSUE_TO_PRODUCTION' => ['debit' => 'WIP', 'credit' => 'STOCK'],
        /*
         * A physical count's adjustment (Inventory spec §12). Beyond §2's
         * own table, and deliberately: a count is the one routine way the
         * value of stock moves with nothing arriving or leaving, and
         * leaving it out of the books would put the ledger and the shelf
         * permanently out of step. Mapped as a shortage — stock down,
         * variance expensed — and reversed by the poster when the count
         * found more than expected.
         */
        'STOCK_ADJUSTMENT' => ['debit' => 'STOCK_VARIANCE', 'credit' => 'STOCK'],
        'PURCHASE_RETURN' => ['debit' => 'VENDOR_PAYABLE', 'credit' => 'STOCK'],
        'ADVANCE_PAYMENT' => ['debit' => 'ADVANCE_TO_SUPPLIER', 'credit' => 'BANK'],
        // The only two-credit event: a payable settled partly out of an
        // advance already paid and partly out of the bank.
        'INVOICE_SETTLEMENT' => [
            'debit' => 'VENDOR_PAYABLE',
            'credit' => 'ADVANCE_TO_SUPPLIER',
            'secondary_credit' => 'BANK',
        ],
    ];

    protected $table = 'gl_event_mappings';

    protected $fillable = [
        'company_id', 'event_type', 'debit_account_id', 'credit_account_id',
        'secondary_credit_account_id', 'active', 'notes',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function debitAccount(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'debit_account_id');
    }

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'credit_account_id');
    }

    public function secondaryCreditAccount(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'secondary_credit_account_id');
    }

    /** Both sides named, and switched on. */
    public function isPostable(): bool
    {
        return $this->active
            && $this->debit_account_id !== null
            && $this->credit_account_id !== null;
    }
}
