<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One dyeable trim on a programme: the item, its shade, yards and kilos. Dye House → Production Board (AnnotechERP 17/07).
 */
class DyeTrimProgrammeLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_trim_programme_lines';

    protected $fillable = ['company_id', 'programme_id', 'item', 'colour_id', 'yds', 'qty_kg', 'remarks', 'sort_order'];

    protected function casts(): array
    {
        return ['yds' => 'float', 'qty_kg' => 'float'];
    }
}
