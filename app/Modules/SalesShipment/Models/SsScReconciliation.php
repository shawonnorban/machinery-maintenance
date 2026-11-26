<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A sub-contract reconciliation (SCRC-…): a chalan squared against what came back — pending, matched, short or excess — and reconciled to close it. Sales & Shipment → Sub-Contract (AnnotechERP 21/03).
 */
class SsScReconciliation extends BaseModel
{
    use BelongsToTenant;

    public const STATES = ['PENDING', 'MATCHED', 'SHORT', 'EXCESS', 'RECONCILED'];

    protected $table = 'ss_sc_reconciliations';

    protected $fillable = ['company_id', 'recon_no', 'delivery_id', 'recon_date', 'remarks', 'state', 'reconciled_at', 'reconciled_by', 'reopened_at', 'reopened_by', 'created_by'];

    protected function casts(): array
    {
        return ['recon_date' => 'date', 'reconciled_at' => 'datetime', 'reopened_at' => 'datetime'];
    }
}
