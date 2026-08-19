<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Models;

use App\Modules\Identity\Models\User;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Files\Models\FileAttachment;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One attempt to import a file (ERD Section 21, SRS 33).
 *
 * Kept after the import finishes. Six months later somebody asks where three
 * hundred machines came from, and the answer is this row: which file, whose
 * upload, how many rows failed and why.
 */
class ImportJob extends BaseModel
{
    use BelongsToTenant;

    /**
     * Validation is a state of its own, because the whole point of the flow is
     * that a person sees what would happen before it happens.
     */
    public const STATUSES = [
        'UPLOADED', 'VALIDATING', 'VALIDATED', 'IMPORTING', 'COMPLETED', 'FAILED', 'CANCELLED',
    ];

    protected $table = 'import_jobs';

    protected $fillable = [
        'company_id', 'user_id', 'type', 'file_id', 'original_name', 'status',
        'total_rows', 'valid_rows', 'success_rows', 'failed_rows', 'updated_rows',
        'error_message', 'validated_at', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'validated_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
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

    public function errors(): HasMany
    {
        return $this->hasMany(ImportError::class);
    }

    /** Only a validated file may be imported, and only once. */
    public function isConfirmable(): bool
    {
        return $this->status === 'VALIDATED' && $this->valid_rows > 0;
    }
}
