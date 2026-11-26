<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A particular a section is costed against (Overtime, Floor Rent …). Work Study → Costing (AnnotechERP 14/05).
 */
class WsOhHead extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ws_oh_heads';

    protected $fillable = ['company_id', 'section_id', 'name', 'name_bn', 'sort_order', 'active'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'active' => 'boolean'];
    }
}
