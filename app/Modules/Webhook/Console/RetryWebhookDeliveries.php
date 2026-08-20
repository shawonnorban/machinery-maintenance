<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Console;

use App\Modules\Webhook\Jobs\DeliverWebhook;
use App\Modules\Webhook\Models\WebhookDelivery;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Picks up deliveries whose next attempt is due (ERD Section 22).
 *
 * The schedule lives on the row rather than in the queue, so a flushed queue
 * loses nothing: this command finds whatever is due and asks for it again.
 *
 * Runs every five minutes because the first retry is one minute out. Anything
 * slower would make the shortest step in the backoff meaningless.
 */
class RetryWebhookDeliveries extends Command
{
    protected $signature = 'webhooks:retry {--limit=200 : Most deliveries to requeue in one pass}';

    protected $description = 'Requeue webhook deliveries whose retry time has come';

    public function handle(TenantContext $context): int
    {
        $due = WebhookDelivery::withoutGlobalScope(TenantScope::class)
            ->where('status', 'FAILED')
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', CarbonImmutable::now())
            ->orderBy('next_retry_at')
            ->limit((int) $this->option('limit'))
            ->get();

        foreach ($due as $delivery) {
            // Cleared so a second pass before this one runs does not queue it
            // twice; the attempt itself sets the next one.
            $delivery->forceFill(['next_retry_at' => null])->save();

            DeliverWebhook::dispatch($delivery->id, $delivery->company_id);
        }

        // Each job sets its own company, so on a synchronous queue the sweep
        // ends pointed at whichever tenant happened to be last. Left that way
        // it would quietly become the context of whatever runs next.
        $context->forget();

        $this->info(sprintf('Requeued %d webhook deliveries.', $due->count()));

        return self::SUCCESS;
    }
}
