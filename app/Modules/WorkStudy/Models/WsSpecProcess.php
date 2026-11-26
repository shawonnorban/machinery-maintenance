<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * What a specification says about a process group. Work Study → Garment Spec Entry (AnnotechERP 14/04).
 */
class WsSpecProcess extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ws_spec_processes';

    protected $fillable = ['company_id', 'spec_id', 'garment_part_id', 'specification', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }
}
