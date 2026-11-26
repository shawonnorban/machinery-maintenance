<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** One order line an uploaded spreadsheet brought in, as the file had it, with the style and order it made (Master Data, AnnotechERP 06/01). */
class OrderUploadLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_order_upload_lines';

    protected $fillable = [
        'company_id', 'upload_id', 'row_no', 'buyer_ac_group', 'buyer_ac', 'buyer', 'style', 'job_no', 'sub_style', 'po_no', 'confirm_date', 'ship_date', 'ship_country',
        'item', 'gmt_type', 'colour', 'size', 'qty', 'rate', 'total', 'production_date', 'style_id', 'order_id',
    ];

    protected function casts(): array
    {
        return ['confirm_date' => 'date', 'ship_date' => 'date', 'production_date' => 'date', 'qty' => 'integer', 'rate' => 'float', 'total' => 'float', 'row_no' => 'integer'];
    }
}
