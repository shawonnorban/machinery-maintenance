<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A chemical check a buyer's audit puts a recipe through (Dye Chemical Audit Recipe). Dye House → Master Data (AnnotechERP 17/01).
 */
class DyeAuditCheck extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_audit_checks';

    protected $fillable = ['company_id', 'buyer_id', 'item_name', 'alter_item_name', 'dropped', 'sort_order'];

    protected function casts(): array
    {
        return ['dropped' => 'boolean', 'sort_order' => 'integer'];
    }
}
