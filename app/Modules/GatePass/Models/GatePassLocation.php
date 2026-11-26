<?php

declare(strict_types=1);

namespace App\Modules\GatePass\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A factory or place outside the gate, with who to reach there. Gate Pass → Master Data (AnnotechERP 11/01).
 */
class GatePassLocation extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'gate_pass_locations';

    protected $fillable = ['company_id', 'location_type_id', 'code', 'name', 'contact_person', 'mobile', 'email', 'address', 'remarks', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
