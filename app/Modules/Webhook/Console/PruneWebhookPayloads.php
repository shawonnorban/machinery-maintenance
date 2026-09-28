<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Console;

use App\Modules\Webhook\Models\WebhookDelivery;
use App\Shared\Scopes\TenantScope;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Drops webhook payloads after thirty days, keeping the delivery record
 * (SRS 49.1, ERD Section 22).
 *
 * The payload is a copy of tenant data sitting in a table nobody reads after
 * the week it was sent. The metadata — what was sent, when, what came back —
 * is what an integration argument needs, and it costs almost nothing to keep.
 */
class PruneWebhookPayloads extends Command
{
    private const RETENTION_DAYS = 30;

    protected $signature = 'webhooks:prune';

    protected $description = 'Remove webhook payload bodies older than 30 days, keeping delivery metadata';

    public function handle(): int
    {
        $cutoff = CarbonImmutable::now()->subDays(self::RETENTION_DAYS);

        $pruned = WebhookDelivery::withoutGlobalScope(TenantScope::class)
            ->where('created_at', '<', $cutoff)
            ->whereNotNull('payload_json')
            ->update([
                'payload_json' => null,
                'request_headers_json' => null,
                'response_body_excerpt' => null,
            ]);

        $this->info(sprintf('Pruned payloads from %d webhook deliveries older than %d days.', $pruned, self::RETENTION_DAYS));

        return self::SUCCESS;
    }
}
