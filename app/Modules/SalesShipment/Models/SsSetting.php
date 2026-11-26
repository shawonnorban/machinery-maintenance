<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A company's Sales & Shipment Settings: the two rules, the alert windows, the nine document prefixes and the sequence width. Sales & Shipment → Operation (AnnotechERP 21/01).
 */
class SsSetting extends BaseModel
{
    use BelongsToTenant;

    /** The nine document series, keyed by their column, with Brickland's default prefix. */
    public const PREFIXES = [
        'prefix_amendment' => 'SOA', 'prefix_challan_return' => 'DCR', 'prefix_sc_return' => 'SCRT', 'prefix_sc_reconciliation' => 'SCRC', 'prefix_shipment' => 'SHP',
        'prefix_booking' => 'BKG', 'prefix_invoice' => 'INV', 'prefix_packing_list' => 'PL', 'prefix_handover' => 'DH',
    ];

    protected $table = 'ss_settings';

    protected $fillable = ['company_id', 'ship_against_delivered', 'require_export_docs', 'due_alert_days', 'cert_alert_days', 'prefix_amendment', 'prefix_challan_return', 'prefix_sc_return',
        'prefix_sc_reconciliation', 'prefix_shipment', 'prefix_booking', 'prefix_invoice', 'prefix_packing_list', 'prefix_handover', 'seq_width', 'updated_by'];

    protected function casts(): array
    {
        return ['ship_against_delivered' => 'boolean', 'require_export_docs' => 'boolean', 'due_alert_days' => 'integer', 'cert_alert_days' => 'integer', 'seq_width' => 'integer'];
    }
}
