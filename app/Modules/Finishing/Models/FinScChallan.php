<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Fabric sent off the finishing floor to a sub-contract processor: its challan, gate pass and vehicle; a draft until posted from the register, which marks its batches as out at the processor. Finishing Line → S/C Process (AnnotechERP 18/03).
 */
class FinScChallan extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'POSTED', 'CANCELLED'];

    protected $table = 'fin_sc_challans';

    protected $fillable = ['company_id', 'challan_no', 'challan_date', 'supplier_id', 'store_id', 'gate_pass_no', 'vehicle_no', 'remarks', 'status', 'posted_at', 'posted_by', 'created_by'];

    protected function casts(): array
    {
        return ['challan_date' => 'date', 'posted_at' => 'datetime'];
    }

    public function lines(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FinScChallanLine::class, 'challan_id')->orderBy('sort_order');
    }
}
