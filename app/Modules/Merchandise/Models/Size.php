<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A garment size in its size group, and its order in a size run (Size Directory). Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class Size extends BaseModel
{
    use BelongsToTenant;

    public const GROUPS = ['LETTER', 'NUMERIC', 'AGE'];

    protected $table = 'sizes';

    protected $fillable = ['company_id', 'size_group', 'code', 'name', 'name_bn', 'comp_size_group', 'display_order', 'comp_display_order', 'active'];

    protected function casts(): array
    {
        return ['display_order' => 'integer', 'comp_display_order' => 'integer', 'active' => 'boolean'];
    }
}
