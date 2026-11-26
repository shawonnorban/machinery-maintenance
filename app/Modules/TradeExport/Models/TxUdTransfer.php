<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Entitlement moved between two declarations. Trade & Export → Operations (AnnotechERP 22/02).
 */
class TxUdTransfer extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'tx_ud_transfers';

    protected $fillable = ['company_id', 'material', 'from_ud_id', 'to_ud_id', 'receive_qty', 'export_qty', 'loss_pct', 'loss_qty', 'balance_qty', 'transfer_qty', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['receive_qty' => 'float', 'export_qty' => 'float', 'loss_pct' => 'float', 'loss_qty' => 'float', 'balance_qty' => 'float', 'transfer_qty' => 'float'];
    }
}
