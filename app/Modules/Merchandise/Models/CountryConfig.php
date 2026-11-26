<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A country the company trades with: its currency, dial code, and whether goods ship there (Country Configuration). Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class CountryConfig extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'country_configs';

    protected $fillable = ['company_id', 'code', 'name', 'region', 'currency', 'dial_code', 'is_ship_to', 'active'];

    protected function casts(): array
    {
        return ['is_ship_to' => 'boolean', 'active' => 'boolean'];
    }
}
