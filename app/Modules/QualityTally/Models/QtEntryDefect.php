<?php

declare(strict_types=1);

namespace App\Modules\QualityTally\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A defect tag on a DEFECT tap (the DHU numerator). Quality Tally (AnnotechERP 36).
 */
class QtEntryDefect extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'qt_entry_defects';

    protected $fillable = ['company_id', 'entry_id', 'defect_type_id'];

    protected function casts(): array
    {
        return [];
    }
}
