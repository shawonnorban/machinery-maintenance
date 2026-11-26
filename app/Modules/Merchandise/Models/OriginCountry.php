<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A country goods are made in (Country & Origin Master). Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class OriginCountry extends BaseModel
{
    use BelongsToTenant;

    public const REGIONS = ['ASIAN', 'EU', 'AMERICAS', 'AFRICA', 'OCEANIA', 'MIDDLE_EAST'];

    protected $table = 'origin_countries';

    protected $fillable = ['company_id', 'code', 'name', 'name_bn', 'region', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
