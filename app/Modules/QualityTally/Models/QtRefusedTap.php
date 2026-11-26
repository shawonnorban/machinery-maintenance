<?php

declare(strict_types=1);

namespace App\Modules\QualityTally\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A tap the server refused for good, kept with its reason (AnnotechERP 37 §৩ 3a):
 * the QC's phone lists it as "not accepted" and the manager sees it too.
 */
class QtRefusedTap extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'qt_refused_taps';

    protected $fillable = ['company_id', 'client_uuid', 'phone_id', 'session_id', 'user_id', 'section', 'line_id', 'job_id', 'action', 'occurred_at', 'message', 'payload', 'seen_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'occurred_at' => 'datetime', 'seen_at' => 'datetime'];
    }
}
