<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * An outgoing mail relay and its recipients (Access Control → Mail Service Configuration, AnnotechERP 02/03). The password is never stored: only the name of the environment variable that holds it.
 */
class MailService extends BaseModel
{
    use BelongsToTenant;

    public const ENCRYPTIONS = ['NONE', 'SSL', 'TLS', 'STARTTLS'];

    protected $table = 'mail_services';

    protected $fillable = ['company_id', 'service_name', 'module_code', 'title', 'to_mail', 'cc_mail', 'bcc_mail', 'smtp_host', 'port', 'smtp_username', 'password_env', 'encryption', 'from_name', 'from_address', 'reply_to', 'remarks', 'is_default', 'active'];

    protected function casts(): array
    {
        return ['port' => 'integer', 'is_default' => 'boolean', 'active' => 'boolean'];
    }
}
