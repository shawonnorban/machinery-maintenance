<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A sample as the buyer reads it: sent, commented, approved. Buyer Gateway → Sample Progress View (AnnotechERP 25/02).
 */
class SampleProgress extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'bg_sample_progress';

    protected $fillable = ['company_id', 'style', 'buyer_id', 'sample_type', 'stage', 'sent_on', 'decision_due', 'version', 'courier'];

    protected function casts(): array
    {
        return ['sent_on' => 'date', 'decision_due' => 'date', 'version' => 'integer'];
    }
}
