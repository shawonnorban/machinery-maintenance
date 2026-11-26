<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A numbered revision of a Y/D programme, with why.
 */
class KnitYdRevision extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_yd_revisions';

    protected $fillable = ['company_id', 'program_id', 'rev_no', 'revised_on', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['revised_on' => 'date', 'rev_no' => 'integer'];
    }
}
