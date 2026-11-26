<?php

declare(strict_types=1);

namespace App\Modules\Settings\Services;

use App\Modules\Api\Support\ApiCaller;
use App\Modules\Settings\Models\IntegrationLog;
use Carbon\CarbonImmutable;

/**
 * Writes one line of Settings → Integration Activity Log (AnnotechERP 03/02):
 * a call to or from an outside system and how it went.
 */
class IntegrationLogger
{
    public function record(string $channel, string $event, bool $succeeded, ?string $details = null, ?string $source = null, ?string $target = null, int $records = 1): IntegrationLog
    {
        return IntegrationLog::create([
            'channel' => $channel,
            'event' => $event,
            'source_name' => $source,
            'target' => $target,
            'records' => $records,
            'details' => $details !== null ? mb_substr($details, 0, 2000) : null,
            'status' => $succeeded ? 'SUCCEEDED' : 'FAILED',
            'user_id' => app(ApiCaller::class)->auditUserId(),
            'occurred_at' => CarbonImmutable::now(),
        ]);
    }
}
