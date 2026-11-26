<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A print or embroidery rate (Costing & Budget → Print & Embroidery Rate Setup, AnnotechERP 06/09). */
class EmbellishmentRate extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['PRINT', 'EMBROIDERY'];

    protected $table = 'merch_embellishment_rates';

    protected $fillable = ['company_id', 'type', 'style_id', 'order_id', 'buyer_id', 'supplier_id', 'description', 'rate', 'currency', 'per', 'confirmed', 'confirmed_at', 'confirmed_by', 'created_by'];

    protected function casts(): array
    {
        return ['rate' => 'float', 'confirmed' => 'boolean', 'confirmed_at' => 'datetime'];
    }
}
