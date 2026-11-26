<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * An entry of a reference list the commercial documents pick from (Document Reference Types). Trade & Export → Master Data (AnnotechERP 22/01).
 */
class TxReferenceEntry extends BaseModel
{
    use BelongsToTenant;

    public const LISTS = ['INCO_TERM', 'PAYMENT_TERM', 'DELIVERY_PLACE', 'TRANSIT_PORT', 'LC_TYPE', 'SHIPMENT_MODE', 'BUSINESS_ASSOCIATION', 'FORWARDER'];

    protected $table = 'tx_reference_entries';

    protected $fillable = ['company_id', 'list', 'code', 'name', 'name_bn', 'short_name', 'display_order', 'active'];

    protected function casts(): array
    {
        return ['display_order' => 'integer', 'active' => 'boolean'];
    }
}
