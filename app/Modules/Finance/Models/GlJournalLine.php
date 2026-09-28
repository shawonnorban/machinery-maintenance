<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One side of one posting (Shared Systems spec §2).
 */
class GlJournalLine extends BaseModel
{
    use BelongsToTenant;

    public const SIDES = ['DEBIT', 'CREDIT'];

    protected $table = 'gl_journal_lines';

    protected $fillable = [
        'company_id', 'gl_journal_entry_id', 'gl_account_id', 'side', 'amount', 'memo',
    ];

    protected function casts(): array
    {
        return ['amount' => MoneyCast::class];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(GlJournalEntry::class, 'gl_journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'gl_account_id');
    }
}
