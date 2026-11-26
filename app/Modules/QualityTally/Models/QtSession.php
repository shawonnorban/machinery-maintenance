<?php

declare(strict_types=1);

namespace App\Modules\QualityTally\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A QC's time on a phone, PIN to sign-out (AnnotechERP 37). `uuid` is the phone's own id for it.
 */
class QtSession extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'qt_sessions';

    protected $fillable = ['company_id', 'uuid', 'phone_id', 'inspector_id', 'user_id', 'offline', 'started_at', 'ended_at', 'end_reason'];

    protected function casts(): array
    {
        return ['offline' => 'boolean', 'started_at' => 'datetime', 'ended_at' => 'datetime'];
    }
}
