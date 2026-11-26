<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** The supplier sourced for one trim of an order, and whether the buyer nominated it (Accessories & Trims, AnnotechERP 06/08). */
class TrimSourcing extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'IN_PROGRESS', 'SENT', 'APPROVED', 'REJECTED', 'CANCELLED'];

    protected $table = 'merch_trim_sourcings';

    protected $fillable = ['company_id', 'order_id', 'item_id', 'description', 'supplier_id', 'nominated', 'status', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['nominated' => 'boolean'];
    }
}
