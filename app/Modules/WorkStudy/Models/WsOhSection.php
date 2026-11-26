<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * An overhead section (Garments, Cutting … Head Office) and whether the sheet re-arranges money into it. Work Study → Costing (AnnotechERP 14/05).
 */
class WsOhSection extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ws_oh_sections';

    protected $fillable = ['company_id', 'name', 'name_bn', 'sort_order', 'is_target'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_target' => 'boolean'];
    }
}
