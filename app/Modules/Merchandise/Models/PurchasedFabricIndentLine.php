<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** One fabric of a purchased fabric indent: type, construction, composition, colour, quantity, GSM, DIA and supplier (Trims & Accessories, AnnotechERP 06/06). */
class PurchasedFabricIndentLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_pfab_indent_lines';

    protected $fillable = [
        'company_id', 'indent_id', 'style_fabric_id', 'fabric_type_id', 'description', 'construction', 'composition_id', 'colour_id', 'qty', 'uom', 'gsm', 'dia',
        'supplier_id', 'required_date', 'remarks', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['qty' => 'float', 'required_date' => 'date', 'sort_order' => 'integer'];
    }
}
