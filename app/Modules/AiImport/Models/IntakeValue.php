<?php

declare(strict_types=1);

namespace App\Modules\AiImport\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** One value an AI service pulled out of an intake file, per extraction rule. AI File & PO Import (AnnotechERP 26/01). */
class IntakeValue extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ai_intake_values';

    protected $fillable = ['company_id', 'intake_file_id', 'field', 'erp_column', 'value', 'confidence', 'mandatory'];

    protected function casts(): array
    {
        return ['confidence' => 'float', 'mandatory' => 'boolean'];
    }
}
