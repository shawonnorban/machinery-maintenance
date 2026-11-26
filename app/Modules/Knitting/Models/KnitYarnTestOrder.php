<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Knit Plan & Requisition for Yarn Test: the lab's own requisition of lots to test.
 */
class KnitYarnTestOrder extends BaseModel
{
    use BelongsToTenant;

    /** Waiting for Issue → Issued (the store issues it) → Tested (the lab filed its answer); Cancelled. */
    public const STATUSES = ['WAITING_ISSUE', 'ISSUED', 'TESTED', 'CANCELLED'];

    protected $table = 'knit_yarn_test_orders';

    protected $fillable = ['company_id', 'order_no', 'order_date', 'req_type', 'store_id', 'requested_by', 'attn_to', 'yarn_description', 'knit_card_no', 'remarks', 'status', 'issued_at', 'created_by'];

    protected function casts(): array
    {
        return ['order_date' => 'date', 'issued_at' => 'datetime'];
    }
}
