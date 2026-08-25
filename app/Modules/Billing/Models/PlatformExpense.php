<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Identity\Models\User;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One cost of running the platform.
 *
 * Not tenant-scoped, and not because anybody forgot: these rows belong to the
 * business that runs the product rather than to any customer of it, so
 * BelongsToTenant would be wrong here in the way it is right everywhere else.
 */
class PlatformExpense extends BaseModel
{
    /**
     * A fixed list, so the totals by category mean something. Free text would
     * give "Hosting", "hosting" and "AWS hosting" three separate rows in a
     * summary that exists to be added up.
     *
     * There is no salary or wages category, and that is a decision rather than
     * an oversight: payroll is out of scope for this product, and an expense
     * category is exactly how it would arrive anyway.
     */
    public const CATEGORIES = [
        'HOSTING',
        'DOMAIN',
        'SOFTWARE',
        'MARKETING',
        'EQUIPMENT',
        'PROFESSIONAL_FEES',
        'BANK_CHARGES',
        'OTHER',
    ];

    protected $table = 'platform_expenses';

    protected $fillable = [
        'spent_on', 'category', 'description', 'amount', 'currency',
        'vendor', 'reference', 'recorded_by',
    ];

    protected function casts(): array
    {
        return ['spent_on' => 'immutable_date'];
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
