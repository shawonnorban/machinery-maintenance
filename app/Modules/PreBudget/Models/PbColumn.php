<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One column of the requisition sheet: its place, type, source, formula, colour rules, whether it is a Knit or a Woven column (applies_to) and who may see or edit it. Pre-Budget (AnnotechERP 33/34).
 */
class PbColumn extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'pb_columns';

    protected $fillable = ['company_id', 'sheet', 'group_id', 'key', 'label', 'sort_order', 'type', 'source', 'multiple', 'prefix', 'suffix', 'decimals', 'width', 'vertical', 'frozen', 'required', 'applies_to', 'formula', 'color_rules', 'text_tone', 'bold', 'kpi', 'role_access', 'is_system', 'hidden', 'updated_by'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'multiple' => 'boolean', 'decimals' => 'integer', 'width' => 'integer', 'vertical' => 'boolean', 'frozen' => 'boolean', 'required' => 'boolean', 'color_rules' => 'array', 'role_access' => 'array', 'bold' => 'boolean', 'is_system' => 'boolean', 'hidden' => 'boolean'];
    }
}
