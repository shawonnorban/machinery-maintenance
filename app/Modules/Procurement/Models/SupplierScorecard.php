<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Vendor\Models\Vendor;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How a supplier behaved over one period (Procurement spec §15).
 *
 * The weights are here rather than in the action because they are the
 * factory's policy, not an implementation detail: delivery and quality
 * carry more than price, because a mill that is cheap and late stops a
 * line, and a line stopped costs more than the saving.
 *
 * A component with no data is dropped and the rest renormalised — see
 * `Weighted mean` below. Scoring a missing component as zero would rank a
 * supplier nobody ordered from below one that failed, which is the wrong
 * way round.
 */
class SupplierScorecard extends BaseModel
{
    use BelongsToTenant;

    /**
     * What each component is worth in the overall score.
     *
     * @var array<string, int>
     */
    public const WEIGHTS = [
        'on_time' => 30,
        'quality' => 30,
        'price' => 20,
        'claims' => 20,
    ];

    protected $table = 'supplier_scorecards';

    protected $fillable = [
        'company_id', 'vendor_id', 'period_start', 'period_end',
        'receipt_count', 'dated_receipt_count', 'on_time_count', 'on_time_percent',
        'received_value', 'rejected_value', 'quality_reject_percent',
        'price_index', 'price_score', 'claim_count', 'claim_frequency_percent',
        'overall_score', 'components_json', 'computed_at', 'computed_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'receipt_count' => 'integer',
            'dated_receipt_count' => 'integer',
            'on_time_count' => 'integer',
            'claim_count' => 'integer',
            'on_time_percent' => 'decimal:2',
            'quality_reject_percent' => 'decimal:2',
            'price_index' => 'decimal:4',
            'price_score' => 'decimal:2',
            'claim_frequency_percent' => 'decimal:2',
            'overall_score' => 'decimal:2',
            'received_value' => MoneyCast::class,
            'rejected_value' => MoneyCast::class,
            'components_json' => 'array',
            'computed_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function calculator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'computed_by');
    }

    /** Nothing happened with this supplier in the period. */
    public function isEmpty(): bool
    {
        return $this->overall_score === null;
    }

    /**
     * The 0–5 figure the supplier master carries (Master Data §3).
     *
     * A separate scale on purpose: `vendors.rating` is read by people
     * choosing a supplier from a list, where five stars is the idiom, while
     * a scorecard is read by somebody arguing a specific number.
     */
    public function asRating(): ?string
    {
        if ($this->overall_score === null) {
            return null;
        }

        return bcdiv((string) $this->overall_score, '20', 2);
    }
}
