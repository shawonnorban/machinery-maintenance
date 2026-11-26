<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A line of an order's T&A change history (T&A Followup, AnnotechERP 06/12). */
class OrderTnaEvent extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_order_tna_events';

    protected $fillable = ['company_id', 'order_id', 'user_id', 'message'];
}
