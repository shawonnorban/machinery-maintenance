<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * The applicant a buyer's L/C names, and the address it carries (L/C Applicant Master). Trade & Export → Master Data (AnnotechERP 22/01).
 */
class TxLcApplicant extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'tx_lc_applicants';

    protected $fillable = ['company_id', 'buyer_id', 'name', 'name_bn', 'address', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
