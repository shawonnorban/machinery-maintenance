<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A value of one of Pre-Budget's own short lists (Yarn Type, Wash Type,
 * Additional Process, GSM, Sustainability, Order Type, and Norban's Item,
 * Fabric Type, Fabric / Yarn Composition, Parts and Yarn Count). Pre-Budget →
 * Master Data.
 */
class PbListValue extends BaseModel
{
    use BelongsToTenant;

    public const LISTS = ['YARN_TYPE', 'WASH_TYPE', 'ADDITIONAL_PROCESS', 'GSM', 'SUSTAINABILITY', 'ORDER_TYPE',
        // Norban's own requisition lists (user, 2026-11-20), apart from Merchandise / Knitting's masters.
        'ITEM', 'FABRIC_TYPE', 'FABRIC_COMPOSITION', 'YARN_COMPOSITION', 'PARTS', 'YARN_COUNT'];

    protected $table = 'pb_list_values';

    protected $fillable = ['company_id', 'list', 'name', 'description', 'sort_order', 'active', 'created_by'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'active' => 'boolean'];
    }
}
