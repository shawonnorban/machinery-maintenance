<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** One version of a style's BOM: a draft until approved; the newest approved one is in use. */
class StyleBomVersion extends BaseModel
{
    use BelongsToTenant;

    public const DRAFT = 'DRAFT';

    public const APPROVED = 'APPROVED';

    protected $table = 'merch_style_bom_versions';

    protected $fillable = ['company_id', 'bom_id', 'version', 'status', 'order_qty', 'addl_pct', 'approved_at', 'approved_by'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'order_qty' => 'integer', 'addl_pct' => 'float', 'approved_at' => 'datetime'];
    }
}
