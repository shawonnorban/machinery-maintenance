<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A feedback survey sent to a buyer and the score that came back. Buyer Gateway → Buyer Feedback Survey (AnnotechERP 25/04).
 */
class Survey extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'bg_surveys';

    protected $fillable = ['company_id', 'title', 'buyer_id', 'season', 'sent_on', 'recipients', 'responses', 'score', 'state'];

    protected function casts(): array
    {
        return ['sent_on' => 'date', 'recipients' => 'integer', 'responses' => 'integer', 'score' => 'float'];
    }
}
