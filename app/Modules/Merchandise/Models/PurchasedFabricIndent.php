<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A purchased fabric indent: a requisition for bought-in woven and knit fabric against an order (Trims & Accessories, AnnotechERP 06/06). */
class PurchasedFabricIndent extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED', 'CLOSED'];

    /** Only a draft, or a rejected indent being corrected, is changed or removed. */
    public const EDITABLE = ['DRAFT', 'REJECTED'];

    public const UOMS = ['KG', 'YDS', 'MTR', 'LBS', 'PCS'];

    protected $table = 'merch_pfab_indents';

    protected $fillable = [
        'company_id', 'req_no', 'req_date', 'buyer_office_id', 'order_id', 'supplier_id', 'ship_date', 'gsm', 'dia', 'remarks', 'status', 'submitted_at',
        'decided_at', 'decided_by', 'created_by',
    ];

    protected function casts(): array
    {
        return ['req_date' => 'date', 'ship_date' => 'date', 'submitted_at' => 'datetime', 'decided_at' => 'datetime'];
    }

    public function isEditable(): bool
    {
        return in_array($this->status, self::EDITABLE, true);
    }
}
