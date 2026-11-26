<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A process loss quantity allowed for a booking or cutting quantity band (Process Loss Quantity Setup, Cutting Process Loss Setup). Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class ProcessLossAllowance extends BaseModel
{
    use BelongsToTenant;

    public const STAGES = ['BOOKING', 'CUTTING'];

    protected $table = 'process_loss_allowances';

    protected $fillable = ['company_id', 'stage', 'qty_low', 'qty_high', 'loss_qty', 'add_to_booking', 'fabric_group', 'colour_type', 'effective_from', 'effective_to'];

    protected function casts(): array
    {
        return ['qty_low' => 'decimal:2', 'qty_high' => 'decimal:2', 'loss_qty' => 'decimal:2', 'add_to_booking' => 'boolean', 'effective_from' => 'date', 'effective_to' => 'date'];
    }
}
