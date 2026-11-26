<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A factory a user may raise knitting entries against (User Company & Office Mapping). Knit Production → Master Data (AnnotechERP 15/01).
 */
class KnitUserOffice extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_user_offices';

    protected $fillable = ['company_id', 'user_id', 'factory_id', 'is_primary', 'can_write'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'can_write' => 'boolean'];
    }
}
