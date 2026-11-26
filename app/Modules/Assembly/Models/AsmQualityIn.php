<?php

declare(strict_types=1);

namespace App\Modules\Assembly\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A bundle out of Finishing received onto a quality line, once, with the good pieces finishing sent on. Assembly Floor → Quality Management (AnnotechERP 20/03).
 */
class AsmQualityIn extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'asm_quality_ins';

    protected $fillable = ['company_id', 'bundle_id', 'finishing_out_id', 'in_date', 'section_id', 'floor_id', 'line_id', 'qty', 'created_by'];

    protected function casts(): array
    {
        return ['in_date' => 'date', 'qty' => 'integer'];
    }
}
