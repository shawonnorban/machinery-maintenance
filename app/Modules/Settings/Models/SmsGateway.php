<?php

declare(strict_types=1);

namespace App\Modules\Settings\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * An SMS provider the system sends through (Settings → SMS Gateway, AnnotechERP 03/02). The API key stays in the server environment; a row names the variable.
 */
class SmsGateway extends BaseModel
{
    use BelongsToTenant;

    public const PROVIDERS = ['BULKSMSBD', 'SSL_WIRELESS', 'TWILIO', 'GENERIC_HTTP'];

    protected $table = 'sms_gateways';

    protected $fillable = ['company_id', 'name', 'provider', 'base_url', 'username', 'sender_id', 'api_key_env', 'is_default', 'active', 'last_used_at'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'active' => 'boolean', 'last_used_at' => 'datetime'];
    }
}
