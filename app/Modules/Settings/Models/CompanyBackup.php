<?php

declare(strict_types=1);

namespace App\Modules\Settings\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A zip of the company's own rows (Settings → Backup & Restore, AnnotechERP
 * 03/04). Once its file has aged out the row stays, EXPIRED, to say who took it
 * and when.
 */
class CompanyBackup extends BaseModel
{
    use BelongsToTenant;

    public const KINDS = ['MANUAL', 'PRE_RESTORE'];

    public const STATUSES = ['QUEUED', 'RUNNING', 'READY', 'FAILED', 'EXPIRED'];

    protected $table = 'company_backups';

    protected $fillable = ['company_id', 'kind', 'status', 'file_path', 'size_bytes', 'checksum', 'tables_json', 'requested_by', 'started_at', 'finished_at', 'error'];

    protected function casts(): array
    {
        return ['tables_json' => 'array', 'size_bytes' => 'integer', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function isBusy(): bool
    {
        return in_array($this->status, ['QUEUED', 'RUNNING'], true);
    }
}
