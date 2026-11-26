<?php

declare(strict_types=1);

namespace App\Modules\GeneralStore\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use RuntimeException;

/**
 * One line of the store's audit trail: who did what, on which screen, and
 * when. Permanent. General Store (AnnotechERP 10), read by Administration →
 * Audit Log.
 */
class GsAuditEntry extends BaseModel
{
    use BelongsToTenant;

    public $timestamps = false;

    /** To the microsecond, so entries a single request writes keep their order. */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $table = 'gs_audit_trail';

    protected $fillable = ['company_id', 'user_id', 'action', 'screen', 'reference', 'summary', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Audit trail entries are permanent and cannot be edited.'));
        static::deleting(fn () => throw new RuntimeException('Audit trail entries are permanent and cannot be deleted.'));
    }
}
