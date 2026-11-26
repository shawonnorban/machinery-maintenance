<?php

declare(strict_types=1);

namespace App\Modules\Assembly\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A bundle out of sewing received onto a finishing line, once, with what sewing sent out. Assembly Floor → Finishing (AnnotechERP 20/02).
 */
class AsmFinishingIn extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'asm_finishing_ins';

    protected $fillable = ['company_id', 'bundle_id', 'sewing_out_id', 'in_date', 'section_id', 'floor_id', 'line_id', 'qty', 'created_by'];

    protected function casts(): array
    {
        return ['in_date' => 'date', 'qty' => 'integer'];
    }
}
