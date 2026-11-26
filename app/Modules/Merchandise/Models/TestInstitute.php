<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A test lab a buyer may send goods to (Buyer Test Lab Mapping). Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class TestInstitute extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'test_institutes';

    protected $fillable = ['company_id', 'name', 'name_bn', 'address', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
