<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A print design and strike-off request: who asks, of whom, on what date (Print Design Sheet, AnnotechERP 06/10). */
class PrintProgram extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_print_programs';

    protected $fillable = ['company_id', 'program_no', 'buyer_id', 'buyer_office_id', 'requisition_date', 'program_by', 'request_to', 'created_by'];

    protected function casts(): array
    {
        return ['requisition_date' => 'date'];
    }
}
