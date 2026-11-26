<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** Opening stock of finished fabric carried into the period, by receiving store (Trims & Accessories, AnnotechERP 06/06). */
class FinishedFabricOpeningBalance extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_ffab_obs';

    protected $fillable = ['company_id', 'ob_no', 'store_id', 'ob_date', 'order_id', 'currency', 'conv_rate', 'usd_bdt', 'created_by'];

    protected function casts(): array
    {
        return ['ob_date' => 'date', 'conv_rate' => 'float', 'usd_bdt' => 'float'];
    }
}
