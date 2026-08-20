<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One event type an endpoint wants (ERD Section 22).
 *
 * Explicit rather than "send everything". An ERP that only cares about
 * breakdowns should not have to receive, verify and discard every stock
 * movement in the factory.
 */
class WebhookSubscription extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'webhook_subscriptions';

    protected $fillable = ['company_id', 'webhook_endpoint_id', 'event_type'];

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }
}
