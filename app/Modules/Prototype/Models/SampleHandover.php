<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** An approved sample handed over or couriered (HO-2026-00001), tracked to the received confirmation. */
class SampleHandover extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['READY', 'HANDOVER_PENDING', 'HANDED_OVER', 'COURIERED', 'IN_TRANSIT', 'DELIVERED', 'RECEIVED', 'CANCELLED'];

    /** Where the sample has reached the buyer. */
    public const ARRIVED = ['DELIVERED', 'RECEIVED'];

    protected $table = 'proto_handovers';

    protected $fillable = ['company_id', 'handover_no', 'sample_id', 'version', 'qty', 'handover_date', 'handover_from', 'handover_to', 'department_id', 'status', 'courier', 'tracking_no', 'expected_delivery', 'actual_delivery', 'received_by', 'received_date', 'remarks', 'attachment'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'qty' => 'integer', 'handover_date' => 'date', 'expected_delivery' => 'date', 'actual_delivery' => 'date', 'received_date' => 'date'];
    }
}
