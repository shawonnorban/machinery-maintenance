<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A printer the knitting floor prints roll and batch tags on, and who it is offered to (Label Printer Configuration). Knit Production → Master Data (AnnotechERP 15/01).
 */
class KnitLabelPrinter extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_label_printers';

    protected $fillable = ['company_id', 'name', 'address', 'user_ids', 'auto_scale', 'display_order', 'active'];

    protected function casts(): array
    {
        return ['user_ids' => 'array', 'auto_scale' => 'boolean', 'display_order' => 'integer', 'active' => 'boolean'];
    }
}
