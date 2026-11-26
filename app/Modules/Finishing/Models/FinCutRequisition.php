<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A cutting floor's request for finished fabric and what the store released against it, lot by lot. Finishing Line → Fabric Store (AnnotechERP 18/05).
 */
class FinCutRequisition extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'PARTIAL', 'COMPLETE', 'CANCELLED'];

    protected $table = 'fin_cut_requisitions';

    protected $fillable = ['company_id', 'req_no', 'req_date', 'cut_prog_ref', 'store_id', 'buyer_id', 'style', 'colour', 'batch_no', 'action', 'status', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['req_date' => 'date'];
    }

    public function lines(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FinCutRequisitionLine::class, 'requisition_id')->orderBy('sort_order');
    }
}
