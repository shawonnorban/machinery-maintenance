<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A utilisation declaration over B2B L/Cs. Trade & Export → Operations (AnnotechERP 22/02).
 */
class TxUd extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'tx_uds';

    protected $fillable = ['company_id', 'ud_no', 'ud_date', 'authority', 'amendment_no', 'final', 'created_by'];

    protected function casts(): array
    {
        return ['ud_date' => 'date', 'final' => 'boolean', 'amendment_no' => 'integer'];
    }
}
