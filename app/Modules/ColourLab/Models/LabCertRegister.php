<?php

declare(strict_types=1);

namespace App\Modules\ColourLab\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One entry in the Certificate Issue Register: a copy issued, handed over, returned, renewed or surrendered, numbered in sequence for the month. Colour Lab → Operations (AnnotechERP 16/01).
 */
class LabCertRegister extends BaseModel
{
    use BelongsToTenant;

    public const ACTIONS = ['ISSUED', 'HANDED_OVER', 'RETURNED', 'RENEWED', 'SURRENDERED'];

    protected $table = 'lab_cert_register';

    protected $fillable = ['company_id', 'register_no', 'certificate_id', 'entry_date', 'action', 'issued_to', 'buyer_id', 'copies', 'due_date', 'return_date', 'handed_by', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['entry_date' => 'date', 'due_date' => 'date', 'return_date' => 'date', 'copies' => 'integer'];
    }
}
