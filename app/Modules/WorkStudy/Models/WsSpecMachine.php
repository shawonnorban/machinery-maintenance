<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * How many of a machine type a specification needs. Work Study → Garment Spec Entry (AnnotechERP 14/04).
 */
class WsSpecMachine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ws_spec_machines';

    protected $fillable = ['company_id', 'spec_id', 'machine_type', 'requirement', 'sort_order'];

    protected function casts(): array
    {
        return ['requirement' => 'integer', 'sort_order' => 'integer'];
    }
}
