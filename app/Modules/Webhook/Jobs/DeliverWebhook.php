<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Jobs;

use App\Modules\Webhook\Models\WebhookDelivery;
use App\Modules\Webhook\Services\WebhookDeliverer;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * One delivery attempt (ERD Section 22).
 *
 * Laravel's own retry machinery is deliberately not used: the backoff schedule
 * belongs to the delivery row so a customer can see when the next attempt is
 * due, and so an attempt survives a queue being flushed. The job asks the
 * deliverer to try once and schedules the next attempt itself.
 *
 * The company travels with the job and is restored before anything is read.
 * A worker has no request and no user, so without this the endpoint behind the
 * delivery is invisible — and a retry swept up by the scheduler while another
 * company's context happened to be set would look, to every tenant-scoped
 * query, like an endpoint that no longer exists.
 */
class DeliverWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** One attempt per job. The retry schedule lives on the delivery row. */
    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        private readonly string $deliveryId,
        private readonly string $companyId,
    ) {}

    public function handle(TenantContext $context, WebhookDeliverer $deliverer): void
    {
        $context->forget();
        $context->set($this->companyId);

        $delivery = WebhookDelivery::withoutGlobalScope(TenantScope::class)->find($this->deliveryId);

        if ($delivery === null || $delivery->succeeded()) {
            return;
        }

        $deliverer->attempt($delivery);
    }
}
