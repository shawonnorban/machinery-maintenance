<?php

declare(strict_types=1);

namespace App\Modules\GatePass\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * What a gate pass is: main (an employee going out) or item (products and materials). Gate Pass → Master Data (AnnotechERP 11/01).
 */
class GatePassCategory extends BaseModel
{
    use BelongsToTenant;

    public const KINDS = ['MAIN', 'ITEM'];

    protected $table = 'gate_pass_categories';

    protected $fillable = ['company_id', 'code', 'name', 'kind', 'description', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
