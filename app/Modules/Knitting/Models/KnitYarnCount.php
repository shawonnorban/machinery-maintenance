<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A yarn count every yarn record is built from (Yarn Count Master). Knit Production → Master Data (AnnotechERP 15/01).
 */
class KnitYarnCount extends BaseModel
{
    use BelongsToTenant;

    public const SYSTEMS = ['Ne', 'Nm', 'D', 'Tex', 'Dtex'];

    protected $table = 'knit_yarn_counts';

    protected $fillable = ['company_id', 'count_no', 'count_system', 'description', 'count_value', 'is_single', 'display_order', 'active'];

    protected function casts(): array
    {
        return ['count_value' => 'decimal:2', 'is_single' => 'boolean', 'display_order' => 'integer', 'active' => 'boolean'];
    }
}
