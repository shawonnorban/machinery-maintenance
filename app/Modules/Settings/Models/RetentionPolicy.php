<?php

declare(strict_types=1);

namespace App\Modules\Settings\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * How long one kind of the company's data is kept (Settings → Data Retention &
 * Purge, AnnotechERP 03/04). A category without a row follows its default.
 */
class RetentionPolicy extends BaseModel
{
    use BelongsToTenant;

    /** category => [default days, minimum days] */
    public const CATEGORIES = [
        'NOTIFICATIONS' => [180, 30],
        'INTEGRATION_LOGS' => [90, 7],
        'WEBHOOK_DELIVERIES' => [90, 7],
        'EXPORT_FILES' => [30, 1],
        'BACKUPS' => [30, 7],
        'AUDIT_LOGS' => [3650, 365],
    ];

    protected $table = 'retention_policies';

    protected $fillable = ['company_id', 'category', 'keep_days', 'active', 'last_purged_at', 'last_purged_count'];

    protected function casts(): array
    {
        return ['keep_days' => 'integer', 'active' => 'boolean', 'last_purged_at' => 'datetime', 'last_purged_count' => 'integer'];
    }
}
