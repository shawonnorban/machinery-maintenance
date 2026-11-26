<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A party a buyer's shipping documents notify (Buyer-Wise Notify Party). Trade & Export → Master Data (AnnotechERP 22/01).
 */
class TxNotifyParty extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'tx_notify_parties';

    protected $fillable = ['company_id', 'buyer_id', 'code', 'name', 'name_bn', 'address', 'country_id', 'post_code', 'po_box', 'phone', 'mobile', 'fax', 'email', 'web_url', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
