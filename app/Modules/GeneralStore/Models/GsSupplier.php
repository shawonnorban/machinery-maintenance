<?php

declare(strict_types=1);

namespace App\Modules\GeneralStore\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A supplier the store buys from, with its contact details (Supplier). General Store → Master Data (AnnotechERP 10/01).
 */
class GsSupplier extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'gs_suppliers';

    protected $fillable = ['company_id', 'code', 'name', 'contact_person', 'mobile', 'email', 'address', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
