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
    ];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
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
