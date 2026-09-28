<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing wrong with one cell (ERD Section 21).
 *
 * Row number counts the header as row 1, because the person fixing the file is
 * looking at a spreadsheet and an index that disagrees with what they see is
 * worse than no index at all.
 */
class ImportError extends BaseModel
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'import_errors';

    protected $fillable = [
        'company_id', 'import_job_id', 'row_number', 'field', 'error', 'value', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(ImportJob::class, 'import_job_id');
    }
}
