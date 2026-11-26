<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A line of a sample programme: what the sample room is asked for — type,
 * item, colour, size and quantity (Sample Lines, AnnotechERP 06/10). Planned
 * until Sample Production pulls it into a batch; then the batch owns it.
 */
class SampleLine extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['PLANNED', 'IN_BATCH', 'PRODUCED', 'DELIVERED'];

    protected $table = 'merch_sample_lines';

    protected $fillable = ['company_id', 'program_id', 'sample_type_id', 'item', 'colour', 'size', 'req_qty', 'unit', 'remarks', 'status', 'batch_no', 'sort_order', 'batch_id', 'sewn_qty', 'delivered_qty', 'rejected_qty', 'batch_remarks'];

    protected function casts(): array
    {
        return ['req_qty' => 'float', 'sewn_qty' => 'float', 'delivered_qty' => 'float', 'rejected_qty' => 'float'];
    }

    /** In a production batch, the line is the batch's: shown, but no longer edited or removed. */
    public function inBatch(): bool
    {
        return $this->batch_no !== null || $this->status !== 'PLANNED';
    }
}
