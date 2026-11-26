<?php

declare(strict_types=1);

namespace App\Modules\OrderSummary\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * When the edit mail goes out (the admin's times of day) and through which mail service. Order Summary (AnnotechERP 32).
 */
class OsMailSetting extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'os_mail_settings';

    protected $fillable = ['company_id', 'enabled', 'times', 'mail_service_id', 'instant_enabled', 'instant_columns', 'last_sent_at', 'last_slot', 'updated_by'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'times' => 'array', 'last_sent_at' => 'datetime', 'instant_enabled' => 'boolean', 'instant_columns' => 'array'];
    }
}
