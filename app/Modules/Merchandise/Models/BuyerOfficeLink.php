<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * An office that acts for a buyer. Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class BuyerOfficeLink extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'buyer_office_links';

    protected $fillable = ['company_id', 'buyer_id', 'buyer_office_id'];
}
