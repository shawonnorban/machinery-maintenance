<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Models;

use App\Modules\Identity\Models\User;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One amendment to a letter of credit (Procurement spec §10).
 *
 * Its own row rather than an edit, for two reasons a trade-finance audit
 * cares about: the bank charges a fee for each amendment, and the reason
 * for each one is the story of how the order moved. Folding them into the
 * current amount loses both.
 */
class LetterOfCreditAmendment extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'letter_of_credit_amendments';

    protected $fillable = [
        'company_id', 'letter_of_credit_id', 'amendment_no', 'amendment_date',
        'previous_amount', 'new_amount', 'previous_expiry_date', 'new_expiry_date',
        'bank_charge', 'currency', 'reason', 'amended_by',
    ];

    protected function casts(): array
    {
        return [
            'amendment_no' => 'integer',
            'amendment_date' => 'date',
            'previous_amount' => MoneyCast::class,
            'new_amount' => MoneyCast::class,
            'bank_charge' => MoneyCast::class,
            'previous_expiry_date' => 'date',
            'new_expiry_date' => 'date',
        ];
    }

    public function letterOfCredit(): BelongsTo
    {
        return $this->belongsTo(LetterOfCredit::class);
    }

    public function amender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'amended_by');
    }

    /** What this amendment moved the commitment by. */
    public function amountChange(): string
    {
        return bcsub((string) $this->new_amount, (string) $this->previous_amount, 4);
    }
}
