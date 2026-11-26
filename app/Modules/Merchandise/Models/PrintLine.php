<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A style / order on a print design sheet, what is asked for it and its uploaded artwork (AnnotechERP 06/10). */
class PrintLine extends BaseModel
{
    use BelongsToTenant;

    public const PRIORITIES = ['LOW', 'NORMAL', 'HIGH', 'URGENT'];

    protected $table = 'merch_print_lines';

    protected $fillable = [
        'company_id', 'program_id', 'style_id', 'order_id', 'sample_type_id', 'material_description', 'nature_of_work', 'shipment_date', 'item', 'old_style_no',
        'print_type', 'artwork_file_id', 'artwork_name', 'print_color', 'req_qty', 'unit', 'priority', 'instruction', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['shipment_date' => 'date', 'req_qty' => 'float'];
    }
}
