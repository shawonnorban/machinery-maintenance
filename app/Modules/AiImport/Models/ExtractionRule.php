<?php

declare(strict_types=1);

namespace App\Modules\AiImport\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One field pulled out of one family into one ERP column. AI File & PO Import → Field Extraction Rules (AnnotechERP 26/01).
 */
class ExtractionRule extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ai_extraction_rules';

    protected $fillable = ['company_id', 'field', 'family', 'found_by', 'erp_column', 'data_type', 'accuracy', 'mandatory'];

    protected function casts(): array
    {
        return ['accuracy' => 'float', 'mandatory' => 'boolean'];
    }
}
