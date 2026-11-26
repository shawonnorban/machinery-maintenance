<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** The buyer's verdict on a submitted sample, and the bulk shade decision (Buyer Sample Feedback, AnnotechERP 06/10). */
class SampleFeedback extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['PENDING', 'SUBMITTED', 'APPROVED', 'REJECTED', 'RESUBMIT'];

    protected $table = 'merch_sample_feedback';

    protected $fillable = ['company_id', 'order_id', 'buyer_office_id', 'sample_type_id', 'batch_id', 'submit_date', 'status', 'bulk_shade_approved', 'comments', 'created_by'];

    protected function casts(): array
    {
        return ['submit_date' => 'date', 'bulk_shade_approved' => 'boolean'];
    }
}
