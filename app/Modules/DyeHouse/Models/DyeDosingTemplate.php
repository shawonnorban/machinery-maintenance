<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable dosing curve a lab dip and a bulk batch are programmed from (Dosing
 * Template Master). Until it is finalised it stays off every lab dip's list.
 * Dye House → Lab-Dip (AnnotechERP 17/02).
 */
class DyeDosingTemplate extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['PRE_TREATMENT', 'DYEING', 'POST_TREATMENT', 'FINISHING', 'WASH'];

    protected $table = 'dye_dosing_templates';

    protected $fillable = ['company_id', 'code', 'name', 'short_name', 'dye_method_id', 'colour_group_id', 'template_type', 'is_finalized', 'created_by'];

    protected function casts(): array
    {
        return ['is_finalized' => 'boolean'];
    }

    public function agents(): HasMany
    {
        return $this->hasMany(DyeDosingTemplateAgent::class, 'template_id')->orderBy('step_no');
    }
}
