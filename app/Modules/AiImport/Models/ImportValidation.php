<?php

declare(strict_types=1);

namespace App\Modules\AiImport\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A check on extracted values before they land. AI File & PO Import → Data Validation Engine (AnnotechERP 26/01).
 */
class ImportValidation extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ai_validations';

    protected $fillable = ['company_id', 'name', 'kind', 'applies_to', 'on_failure', 'triggered', 'pass_rate', 'enabled'];

    protected function casts(): array
    {
        return ['triggered' => 'integer', 'pass_rate' => 'float', 'enabled' => 'boolean'];
    }
}
