<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Whether a user may raise, and whether they may approve, a voucher type (Maker-Checker Setup). Finance & Ledger → Master Data (AnnotechERP 23/01).
 */
class AccVoucherGrant extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'acc_voucher_grants';

    protected $fillable = ['company_id', 'voucher_type', 'user_id', 'can_make', 'can_check'];

    protected function casts(): array
    {
        return ['can_make' => 'boolean', 'can_check' => 'boolean'];
    }
}
