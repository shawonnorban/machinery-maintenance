<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A process a style goes through (Services → Process Details, AnnotechERP 06/05). */
class StyleProcess extends BaseModel
{
    use BelongsToTenant;

    public const PROCESSES = ['YD', 'DYEING', 'PRINT', 'EMBROIDERY', 'WASH', 'AOP', 'HEAT_SEAL', 'GARMENT_DYE'];

    protected $table = 'merch_style_processes';

    protected $fillable = ['company_id', 'style_id', 'process', 'type', 'supplier_id', 'remarks', 'image_id', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }
}
