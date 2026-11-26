<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A dyeing method a batch may be run under, single or double (Dye Method Master). Dye House → Master Data (AnnotechERP 17/01).
 */
class DyeMethod extends BaseModel
{
    use BelongsToTenant;

    public const SD = ['SINGLE', 'DOUBLE'];

    protected $table = 'dye_methods';

    protected $fillable = ['company_id', 'name', 'short_name', 'sd', 'is_default', 'active'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'active' => 'boolean'];
    }
}
