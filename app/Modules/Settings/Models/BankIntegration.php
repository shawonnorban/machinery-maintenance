<?php

declare(strict_types=1);

namespace App\Modules\Settings\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A bank account the system exchanges payments or statements with (Settings → Bank & Payment Integration, AnnotechERP 03/02).
 */
class BankIntegration extends BaseModel
{
    use BelongsToTenant;

    public const CHANNELS = ['API', 'SFTP', 'FILE'];

    public const FILE_FORMATS = ['NONE', 'CSV', 'MT940', 'ISO20022'];

    protected $table = 'bank_integrations';

    protected $fillable = ['company_id', 'bank_name', 'account_name', 'account_no', 'routing_no', 'swift_code', 'channel', 'file_format', 'endpoint', 'credential_env', 'active', 'last_tested_at', 'last_test_status'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'last_tested_at' => 'datetime'];
    }
}
