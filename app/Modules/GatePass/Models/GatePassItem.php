<?php

declare(strict_types=1);

namespace App\Modules\GatePass\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A product or material a gate pass carries out. Gate Pass → Master Data (AnnotechERP 11/01).
 */
class GatePassItem extends BaseModel
{
    use BelongsToTenant;

    public const CATEGORIES = ['FINISHED_GARMENTS', 'FABRIC', 'TRIMS', 'SAMPLE_GARMENTS', 'PACKING_MATERIAL', 'MACHINERY', 'EQUIPMENT', 'DOCUMENTS', 'OTHER'];

    public const UNITS = ['PCS', 'DZN', 'SET', 'KG', 'YDS', 'MTR', 'ROLL', 'CTN', 'BOX', 'BAG', 'LTR', 'NOS'];

    protected $table = 'gate_pass_items';

    protected $fillable = ['company_id', 'code', 'name', 'item_category', 'unit', 'description', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
