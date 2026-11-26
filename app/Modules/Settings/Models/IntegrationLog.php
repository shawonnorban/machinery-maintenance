<?php

declare(strict_types=1);

namespace App\Modules\Settings\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One call to or from an outside system and how it went (Settings → Integration Activity Log, AnnotechERP 03/02).
 */
class IntegrationLog extends BaseModel
{
    use BelongsToTenant;

    public const CHANNELS = ['SMS', 'BANK', 'MAIL', 'WEBHOOK', 'API', 'DEVICE'];

    public const STATUSES = ['SUCCEEDED', 'FAILED'];

    protected $table = 'integration_logs';

    protected $fillable = ['company_id', 'channel', 'event', 'source_name', 'target', 'records', 'details', 'status', 'user_id', 'occurred_at'];

    protected function casts(): array
    {
        return ['records' => 'integer', 'occurred_at' => 'datetime'];
    }
}
