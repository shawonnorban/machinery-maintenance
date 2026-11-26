<?php

declare(strict_types=1);

namespace App\Modules\Settings\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * An uploaded backup on its way back in (Settings → Backup & Restore,
 * AnnotechERP 03/04): checked, previewed, then applied as a merge.
 */
class CompanyRestore extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['INVALID', 'PREVIEWED', 'QUEUED', 'RUNNING', 'APPLIED', 'FAILED'];

    protected $table = 'company_restores';

    protected $fillable = ['company_id', 'file_path', 'original_name', 'status', 'manifest_json', 'preview_json', 'pre_restore_backup_id', 'requested_by', 'applied_by', 'applied_at', 'error'];

    protected function casts(): array
    {
        return ['manifest_json' => 'array', 'preview_json' => 'array', 'applied_at' => 'datetime'];
    }
}
