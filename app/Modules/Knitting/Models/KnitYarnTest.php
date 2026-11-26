<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * What the lab measured, filed against one test order.
 */
class KnitYarnTest extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_yarn_tests';

    protected $fillable = ['company_id', 'test_no', 'order_id', 'test_date', 'test_by', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['test_date' => 'date'];
    }
}
