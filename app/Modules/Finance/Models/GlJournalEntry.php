<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One posting to the books (Shared Systems spec §2).
 *
 * Header and lines rather than a debit column and a credit column: the
 * settlement event already credits two accounts, and a two-column shape
 * would have to be rebuilt the first time finance asked for a third.
 *
 * It carries the source document's number as well as its id, because an
 * accountant looks a posting up by "GRN-DHK-2026-0041", not by a ULID.
 */
class GlJournalEntry extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'gl_journal_entries';

    protected $fillable = [
        'company_id', 'factory_id', 'event_type', 'entry_date', 'amount', 'currency',
        'source_type', 'source_id', 'source_no', 'memo', 'posted_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'amount' => MoneyCast::class,
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GlJournalLine::class);
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /** Debits less credits — zero, or the entry should never have been written. */
    public function imbalance(): string
    {
        $this->loadMissing('lines');

        return $this->lines->reduce(
            fn (string $sum, GlJournalLine $line): string => $line->side === 'DEBIT'
                ? bcadd($sum, (string) $line->amount, 4)
                : bcsub($sum, (string) $line->amount, 4),
            '0.0000',
        );
    }

    public function isBalanced(): bool
    {
        return bccomp($this->imbalance(), '0', 4) === 0;
    }
}
