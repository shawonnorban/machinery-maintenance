<?php

declare(strict_types=1);

namespace App\Modules\GeneralStore\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * The store's own settings: requisition approval, the issue rules and the name on vouchers. General Store → Administration (AnnotechERP 10/07).
 */
class GsSetting extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'gs_settings';

    protected $fillable = ['company_id', 'requisition_approval', 'direct_issue', 'receiver_required', 'voucher_name', 'voucher_footer', 'updated_by'];

    protected $attributes = ['requisition_approval' => true, 'direct_issue' => true, 'receiver_required' => false];

    protected function casts(): array
    {
        return ['requisition_approval' => 'boolean', 'direct_issue' => 'boolean', 'receiver_required' => 'boolean'];
    }
}
