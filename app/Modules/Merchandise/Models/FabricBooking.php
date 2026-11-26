<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Modules\Approval\Contracts\Approvable;
use App\Modules\Approval\Models\ApprovalAction;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Carbon\CarbonImmutable;

/**
 * Fabric booked against an order: bulk, additional, buffer or imported (Fabric Booking, AnnotechERP 06/11).
 *
 * A bulk booking is approved through the Approval Center: sent, it waits as
 * Submitted and cannot be changed; the verdict moves it to Approved (onto
 * the Approved Fabric Booking List) or Rejected, and a return puts it back
 * to Draft. A company with no fabric booking chain approves on sending.
 */
class FabricBooking extends BaseModel implements Approvable
{
    use BelongsToTenant;

    public const TYPES = ['BULK', 'ADDITIONAL', 'BUFFER', 'IMPORT', 'PROJECTION', 'SAMPLE'];

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED', 'CANCELLED'];

    /** Each booking type is numbered on its own, as Brickland numbers them. */
    public const PREFIXES = ['BULK' => 'BFB-', 'ADDITIONAL' => 'BK-ADD-', 'BUFFER' => 'BK-BUF-', 'IMPORT' => 'BK-IMP-', 'PROJECTION' => 'PFB-', 'SAMPLE' => 'SFB-'];

    /** The screen each type is keyed on, for the approver's link. */
    public const SCREENS = ['BULK' => 'bulk-fabric-booking', 'ADDITIONAL' => 'additional-fabric-booking', 'BUFFER' => 'buffer-booking-purchase', 'IMPORT' => 'import-fabric-booking-purchase'];

    public const CURRENCIES = ['USD', 'EUR', 'GBP', 'BDT'];

    /** With the approvers, or approved: the booking is no longer changed. */
    public const LOCKED = ['SUBMITTED', 'APPROVED'];

    protected $table = 'merch_fabric_bookings';

    protected $fillable = [
        'company_id', 'booking_type', 'booking_no', 'revision', 'booking_date', 'program_by', 'buyer_office_id', 'style_id', 'order_id', 'currency', 'conv_rate',
        'item_model', 'work_style_no', 'ref_no', 'sp_no', 'ship_date', 'remarks', 'active', 'cutting_wastage', 'yd_wastage', 'aop_wastage', 'status', 'approved_at', 'approved_by', 'send_mail', 'mail_sent_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'date', 'ship_date' => 'date', 'active' => 'boolean', 'approved_at' => 'datetime', 'mail_sent_at' => 'datetime', 'send_mail' => 'boolean', 'revision' => 'integer',
            'conv_rate' => 'float', 'cutting_wastage' => 'float', 'yd_wastage' => 'float', 'aop_wastage' => 'float',
        ];
    }

    /**
     * A bulk booking is held while with the approvers and once approved; an import booking once approved.
     * Additional and buffer bookings carry their status on the form and stay open.
     */
    public function isLocked(): bool
    {
        return match ($this->booking_type) {
            'BULK' => in_array($this->status, self::LOCKED, true),
            'IMPORT' => $this->status === 'APPROVED',
            default => false,
        };
    }

    /** The fabric's value — finished quantity at the line's rate — in the booking's currency. */
    public function approvalContext(): array
    {
        $value = FabricBookingLine::query()->where('booking_id', $this->id)->get(['finish_qty', 'rate'])
            ->sum(fn (FabricBookingLine $l): float => (float) $l->finish_qty * (float) ($l->rate ?? 0));

        return ['cost' => number_format($value, 4, '.', ''), 'currency' => $this->currency ?? 'USD', 'booking_type' => $this->booking_type];
    }

    public function approvalSummary(): array
    {
        $order = $this->order_id ? Order::query()->whereKey($this->order_id)->first(['order_no', 'style_no', 'work_style_no']) : null;
        $screen = self::SCREENS[$this->booking_type] ?? null;
        $path = $screen ? "/merchandise-desk/{$screen}?tab=edit&ref=".rawurlencode((string) $this->booking_no) : '/merchandise-desk/fabric-booking-list-approved';

        return [
            'number' => (string) $this->booking_no,
            'title' => trim(__('merchandise.booking.approval_title', ['no' => $this->booking_no, 'order' => $order ? "{$order->order_no} · ".($order->work_style_no ?: $order->style_no) : '—'])),
            'url' => rtrim((string) config('tenancy.frontend_url'), '/').$path,
            'factory_id' => null,
        ];
    }

    public function onApprovalDecided(string $status): void
    {
        if ($this->status !== 'SUBMITTED') {
            return;
        }

        match ($status) {
            'APPROVED', 'NOT_REQUIRED' => $this->forceFill(['status' => 'APPROVED', 'approved_at' => CarbonImmutable::now(), 'approved_by' => $this->lastApprover()])->save(),
            'REJECTED' => $this->forceFill(['status' => 'REJECTED'])->save(),
            // Returned for revision, or the request withdrawn: back with the merchandiser to change.
            'RETURNED', 'CANCELLED' => $this->forceFill(['status' => 'DRAFT'])->save(),
            default => null,
        };
    }

    /** Whoever signed the last step; nobody when no chain asked for a signature. */
    private function lastApprover(): ?string
    {
        return $this->approval_request_id
            ? ApprovalAction::query()->where('approval_request_id', $this->approval_request_id)->where('action', 'APPROVED')->latest('acted_at')->value('approver_id')
            : null;
    }
}
