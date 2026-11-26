<?php

declare(strict_types=1);

namespace App\Modules\MaterialProcurement\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A supplier's portal login: the external account it signs in with on the
 * normal ERP login page, the contact behind it, and the supplier it quotes
 * for. Switched off rather than deleted, so its quotations keep their author.
 */
class SupplierLogin extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'mp_supplier_logins';

    protected $fillable = ['company_id', 'supplier_id', 'user_id', 'contact_name', 'active', 'created_by'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
