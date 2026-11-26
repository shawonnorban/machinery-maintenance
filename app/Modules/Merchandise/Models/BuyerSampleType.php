<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A sample type a buyer requires, with its own source, size or quantity where it differs (Buyer Sample Mapping). Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class BuyerSampleType extends BaseModel
{
    use BelongsToTenant;

    public const SOURCES = ['IN_HOUSE', 'SUBCONTRACT', 'BUYER'];

    protected $table = 'buyer_sample_types';

    protected $fillable = ['company_id', 'buyer_id', 'sample_type_id', 'source', 'size', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }
}
