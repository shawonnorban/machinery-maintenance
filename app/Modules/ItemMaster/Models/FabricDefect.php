<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Models;

use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * One entry in the Knit or Woven defect catalogue (Inventory spec §4.4) —
 * single source of truth, so an inspection screen and a defect report never
 * disagree on what "Barré" means. Not tenant-scoped: platform rows (null
 * company_id) seed every company's default list, the same pattern as
 * `units_of_measure`; a company can add its own on top.
 */
class FabricDefect extends BaseModel
{
    public const FAMILIES = ['KNIT', 'WOVEN'];

    protected $table = 'fabric_defects';

    protected $fillable = ['company_id', 'fabric_family', 'code', 'name', 'name_bn', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function scopeAvailableTo(Builder $query, string $companyId): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('company_id')->orWhere('company_id', $companyId));
    }
}
