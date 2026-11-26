<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * An entry of one of the four lists a knitting machine is described from (Machine Reference Lists). Knit Production → Master Data (AnnotechERP 15/01).
 */
class KnitRefEntry extends BaseModel
{
    use BelongsToTenant;

    public const LISTS = ['MC_TYPE', 'BRAND', 'DIA', 'GG'];

    protected $table = 'knit_ref_entries';

    protected $fillable = ['company_id', 'list', 'code', 'name', 'sort_order', 'active'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'active' => 'boolean'];
    }
}
