<?php

declare(strict_types=1);

namespace App\Shared\Files\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A stored private file (SRS 13.4).
 */
class FileAttachment extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'file_attachments';

    protected $fillable = [
        'company_id', 'attachable_type', 'attachable_id', 'disk', 'path',
        'original_name', 'mime_type', 'size_bytes', 'sha256', 'uploaded_by',
        'scan_status', 'scanned_at', 'scan_result',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'scanned_at' => 'datetime',
        ];
    }

    /**
     * May this file be served (API 19.1 rule 3)?
     *
     * CLEAN was checked and passed. SKIPPED was never checked, because scanning
     * was off when it arrived — which is a decision the operator made, not a
     * question still outstanding, so the file is usable and the row says why.
     *
     * PENDING and INFECTED both refuse, for opposite reasons: one has not been
     * looked at yet, the other has.
     */
    public function isDownloadable(): bool
    {
        return in_array($this->scan_status, ['CLEAN', 'SKIPPED'], true);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Human-readable size for a listing. A technician deciding whether to open
     * an attachment on a factory-floor connection wants to know it is 4 MB.
     */
    public function humanSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->size_bytes;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, $unit === 0 ? 0 : 1).' '.$units[$unit];
    }
}
