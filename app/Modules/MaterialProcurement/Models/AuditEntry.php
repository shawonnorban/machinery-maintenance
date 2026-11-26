<?php

declare(strict_types=1);

namespace App\Modules\MaterialProcurement\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use RuntimeException;

/**
 * One entry of the module's audit trail: who did what, on which screen, to
 * which document, in a line. Append-only, enforced here — an entry that can
 * be edited or deleted is not evidence.
 */
class AuditEntry extends BaseModel
{
    use BelongsToTenant;

    public const ACTIONS = ['CREATE', 'UPDATE', 'DELETE'];

    public $timestamps = false;

    /** To the microsecond, so entries a single request writes keep their order. */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $table = 'mp_audit_trail';

    protected $fillable = ['company_id', 'user_id', 'action', 'screen', 'reference', 'summary', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('Audit trail entries are permanent and cannot be edited.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('Audit trail entries are permanent and cannot be deleted.');
        });
    }
}
