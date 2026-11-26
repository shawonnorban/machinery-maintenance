<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A supplier's proforma invoice. Trade & Export → Import (AnnotechERP 22/03).
 */
class TxImportPi extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'tx_import_pis';

    protected $fillable = ['company_id', 'tracking_no', 'pi_no', 'supplier_id', 'export_lc_id', 'pi_date', 'pi_amount', 'net_amount', 'remarks', 'status', 'approved_at', 'created_by'];

    protected function casts(): array
    {
        return ['pi_date' => 'date', 'approved_at' => 'datetime', 'pi_amount' => 'float', 'net_amount' => 'float'];
    }
}
