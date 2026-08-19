<?php

declare(strict_types=1);

namespace App\Modules\Audit\Jobs;

use App\Modules\Audit\Models\AuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Writes one audit row (ERD Section 18 rule 4).
 *
 * Queued so an audit write never slows the work it records, and retried hard
 * because a missing row is a hole in evidence. If it still fails, that is
 * logged at critical: a silently lost audit entry is worse than a slow request,
 * and somebody has to know the trail has a gap.
 *
 * The payload is a plain array rather than a model, so nothing here depends on
 * a record that may have been deleted by the time the job runs.
 */
class WriteAuditEntry implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [1, 5, 15, 60];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(private readonly array $payload) {}

    public function handle(): void
    {
        // withoutGlobalScopes is not needed: the model is not tenant-scoped,
        // because a failed login belongs to no company yet.
        AuditLog::create($this->payload);
    }

    public function failed(Throwable $e): void
    {
        Log::critical('Audit entry could not be written; the trail has a gap.', [
            'action' => $this->payload['action'] ?? null,
            'entity_type' => $this->payload['entity_type'] ?? null,
            'entity_id' => $this->payload['entity_id'] ?? null,
            'request_id' => $this->payload['request_id'] ?? null,
            'error' => $e->getMessage(),
        ]);
    }
}
