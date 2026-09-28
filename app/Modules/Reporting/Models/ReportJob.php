<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Models;

use App\Modules\Identity\Models\User;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Files\Models\FileAttachment;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One request for a report (ERD Section 28, SRS 32).
 *
 * The row exists before the work starts and survives its failure. A report that
 * dies halfway through must be visible as a failure with a reason — a request
 * that simply disappears teaches people to run it again, which is how a struggling
 * queue gets three copies of the job that broke it.
 */
class ReportJob extends BaseModel
{
    use BelongsToTenant;

    public const FORMATS = ['CSV', 'XLSX', 'PDF'];

    public const STATUSES = ['QUEUED', 'RUNNING', 'COMPLETED', 'FAILED', 'EXPIRED'];

    protected $table = 'report_jobs';

    protected $fillable = [
        'company_id', 'user_id', 'report_type', 'parameters_json', 'filters_json',
        'format', 'locale', 'status', 'file_id', 'row_count', 'error_message',
        'started_at', 'completed_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'parameters_json' => 'array',
            'filters_json' => 'array',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
