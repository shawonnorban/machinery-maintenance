<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One scan of a bundle into a sample-room process — each bundle once per
 * process — with who worked it, when, against how many expected hours, its
 * status and the reason for any delay.
 */
class BundleMovement extends BaseModel
{
    use BelongsToTenant;

    /** The sample room's processes, in the order a bundle goes through them. */
    public const PROCESSES = ['DELV_PRINT', 'DELV_EMBR', 'DELV_PRINT_EMBR', 'RECV_EMBELLISHMENT', 'DELV_SEWING', 'SEWING_DONE', 'DISTRIBUTION'];

    /** Out to the embellishment house. */
    public const SENT = ['DELV_PRINT', 'DELV_EMBR', 'DELV_PRINT_EMBR'];

    public const STATUSES = ['PENDING', 'ISSUED', 'RECEIVED', 'IN_PROGRESS', 'COMPLETED', 'HOLD', 'DELAYED', 'REJECTED'];

    protected $table = 'proto_bundle_movements';

    protected $fillable = ['company_id', 'bundle_id', 'card_id', 'process', 'movement_date', 'scanned_at', 'scanned_by', 'qty', 'operator_id', 'received_at', 'start_at', 'end_at', 'expected_hours', 'status', 'delay_reason', 'remarks'];

    protected function casts(): array
    {
        return ['movement_date' => 'date', 'scanned_at' => 'datetime', 'received_at' => 'datetime', 'start_at' => 'datetime', 'end_at' => 'datetime', 'qty' => 'integer', 'expected_hours' => 'decimal:2'];
    }

    /** Hours past the expected: what the work took (start to end, or to now while it runs) less what it was expected to take. */
    public function delayHours(): ?float
    {
        if ($this->expected_hours === null || $this->start_at === null) {
            return null;
        }
        $took = $this->start_at->diffInMinutes($this->end_at ?? now()) / 60;

        return round(max(0.0, $took - (float) $this->expected_hours), 2);
    }
}
