<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A test lab a buyer accepts. Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class BuyerTestInstitute extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'buyer_test_institutes';

    protected $fillable = ['company_id', 'buyer_id', 'test_institute_id'];
}
