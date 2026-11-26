<?php

declare(strict_types=1);

namespace App\Modules\GatePass\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One step on a gate pass's audit trail — "Approved → Approved, by whom and
 * when". Only ever added. Gate Pass (AnnotechERP 11/03–11/04).
 */
class GatePassEvent extends BaseModel
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $table = 'gate_pass_events';

    protected $fillable = ['company_id', 'gate_pass_id', 'action', 'to_status', 'note', 'user_id'];
}
