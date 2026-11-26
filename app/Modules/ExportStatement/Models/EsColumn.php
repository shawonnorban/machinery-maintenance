<?php

declare(strict_types=1);

namespace App\Modules\ExportStatement\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One column of the Export Statement sheet: its type, list, format, formula ({key} form), colour rules and who sees it. Export Statement Column Setup (2026-11-21).
 */
class EsColumn extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'es_columns';

    protected $fillable = ['company_id', 'group_id', 'key', 'label', 'sort_order', 'type', 'source', 'multiple', 'prefix', 'suffix', 'decimals', 'width', 'vertical', 'frozen', 'required', 'formula', 'color_rules', 'text_tone', 'bold', 'kpi', 'pair_reduce', 'role_access', 'is_system', 'hidden', 'updated_by'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'multiple' => 'boolean', 'decimals' => 'integer', 'width' => 'integer', 'vertical' => 'boolean', 'frozen' => 'boolean', 'required' => 'boolean', 'color_rules' => 'array', 'role_access' => 'array', 'bold' => 'boolean', 'is_system' => 'boolean', 'hidden' => 'boolean'];
    }
}
