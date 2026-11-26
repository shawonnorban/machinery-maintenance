<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A bank the commercial documents name (Bank Directory). Trade & Export → Master Data (AnnotechERP 22/01).
 */
class TxBank extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'tx_banks';

    protected $fillable = ['company_id', 'code', 'name', 'name_bn', 'prefix', 'is_local', 'is_treasury', 'country_id', 'swift_code', 'iban', 'call_center', 'web_url', 'active'];

    protected function casts(): array
    {
        return ['is_local' => 'boolean', 'is_treasury' => 'boolean', 'active' => 'boolean'];
    }
}
