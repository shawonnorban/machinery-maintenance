<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Models;

use App\Modules\Identity\Models\User;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Files\Models\FileAttachment;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A raw data export (ERD Section 21, SRS 33).
 *
 * Separate from a report job because it answers a different question. A report
 * is a view of the data shaped for reading; an export is the data shaped so it
 * can come back in — the same columns the importer accepts, so a person can
 * pull their asset register out, fix two hundred rows in a spreadsheet, and
 * upload it again.
 */
class ExportJob extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['QUEUED', 'COMPLETED', 'FAILED', 'EXPIRED'];

    protected $table = 'export_jobs';

    protected $fillable = [
        'company_id', 'requested_by', 'type', 'filters_json', 'format',
        'status', 'file_id', 'row_count', 'error_message', 'completed_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'filters_json' => 'array',
            'completed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(FileAttachment::class, 'file_id');
    }

    public function isDownloadable(): bool
    {
        return $this->status === 'COMPLETED'
            && $this->file_id !== null
            && $this->expires_at->isFuture();
    }
}
