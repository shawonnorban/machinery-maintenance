<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A buyer's liaison office, head office, buying house or agent (Buyer Office Directory). Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class BuyerOffice extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['LIAISON', 'HEAD_OFFICE', 'BUYING_HOUSE', 'AGENT'];

    protected $table = 'buyer_offices';

    protected $fillable = ['company_id', 'name', 'office_type', 'address', 'country_id', 'contact_person', 'email', 'phone', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
