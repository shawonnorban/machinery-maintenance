<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A movement on the purchased-fabric stock ledger: a batch into (or out of) a store at its cost in BDT (Trims & Accessories, AnnotechERP 06/06). */
class PurchasedFabricLedger extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_pfab_ledger';

    protected $fillable = [
        'company_id', 'store_id', 'source', 'source_id', 'source_line_id', 'txn_date', 'batch_no', 'fabric_item', 'description', 'colour_id', 'fabric_type_id',
        'composition_id', 'uom', 'qty', 'cost_bdt',
    ];

    protected function casts(): array
    {
        return ['txn_date' => 'date', 'qty' => 'float', 'cost_bdt' => 'float'];
    }
}
