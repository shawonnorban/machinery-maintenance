<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Tenancy\Models\Factory;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a customer actually used (ERD Section 19, ADR-028).
 *
 * Measured whether or not it is billed. A renewal negotiated on "you have about
 * forty machines" against a system that knows it was 412 is a conversation
 * nobody wins; measuring independently of pricing means the next contract is
 * priced from evidence.
 */
class UsageMetric extends BaseModel
{
    use BelongsToTenant;

    public const METRICS = [
        'ACTIVE_USERS', 'ACTIVE_FACTORIES', 'ACTIVE_ASSETS',
        'WORK_ORDERS_CREATED', 'STORAGE_BYTES', 'API_CALLS', 'WEBHOOK_DELIVERIES',
    ];

    /** The ones a contract can set a limit against. */
    public const LIMITED_METRICS = ['ACTIVE_USERS', 'ACTIVE_FACTORIES', 'ACTIVE_ASSETS'];

    protected $table = 'usage_metrics';

    protected $fillable = [
        'company_id', 'factory_id', 'metric', 'value', 'limit_value',
        'exceeded', 'measured_at', 'period_start', 'period_end',
    ];

    protected function casts(): array
    {
        return [
            'measured_at' => 'immutable_datetime',
            'period_start' => 'immutable_date',
            'period_end' => 'immutable_date',
            'exceeded' => 'boolean',
        ];
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }
}
