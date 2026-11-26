<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A collar / cuff measurement chart of a style (Collar & Cuff Measurement Sheet, AnnotechERP 06/10). */
class MeasureChart extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'IN_PROGRESS', 'SENT', 'APPROVED', 'REJECTED', 'CANCELLED'];

    protected $table = 'merch_measure_charts';

    protected $fillable = ['company_id', 'style_id', 'buyer_office_id', 'sub_style', 'item_name', 'model_no', 'status', 'created_by'];

    protected function casts(): array
    {
        return [];
    }
}
