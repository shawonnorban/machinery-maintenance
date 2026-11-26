<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * An entry of a list the machine form picks from — a machine type, a brand or a location (Machine Reference Lists). Dye House → Master Data (AnnotechERP 17/01).
 */
class DyeMachineList extends BaseModel
{
    use BelongsToTenant;

    public const LISTS = ['TYPE', 'BRAND', 'LOCATION'];

    protected $table = 'dye_machine_lists';

    protected $fillable = ['company_id', 'list', 'name', 'sort_order', 'active'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'active' => 'boolean'];
    }
}
