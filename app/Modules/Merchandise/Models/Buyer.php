<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A buyer account every order, style and costing sheet is filed against (Buyer Account Master). Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class Buyer extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'buyers';

    protected $fillable = ['company_id', 'code', 'name', 'name_bn', 'short_name', 'profile', 'pi_address', 'contact_person', 'country_id', 'bank_name', 'bank_id', 'supplier_id', 'registered_from', 'registered_to', 'display_order', 'active'];

    protected function casts(): array
    {
        return ['registered_from' => 'date', 'registered_to' => 'date', 'display_order' => 'integer', 'active' => 'boolean'];
    }
}
