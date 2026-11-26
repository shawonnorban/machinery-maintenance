<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

/**
 * Process Loss Quantity Setup: allowances used when booking fabric. Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class BookingLossAllowanceResource extends ProcessLossAllowanceResource
{
    public function key(): string
    {
        return 'booking-loss-allowances';
    }

    protected function stage(): string
    {
        return 'BOOKING';
    }
}
