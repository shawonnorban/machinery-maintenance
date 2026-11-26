<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A sample production batch: opened on a date, the lines pulled into it, closed on submit (Sample Production Entry, AnnotechERP 06/10). */
class SampleBatch extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'SUBMITTED'];

    /** The sample card the batch's lines are printed on. */
    public const CARD_FORMATS = ['LINDEX', 'OTHER'];

    protected $table = 'merch_sample_batches';

    protected $fillable = ['company_id', 'batch_no', 'batch_date', 'card_format', 'status', 'submitted_at', 'submitted_by', 'created_by'];

    protected function casts(): array
    {
        return ['batch_date' => 'date', 'submitted_at' => 'datetime'];
    }
}
