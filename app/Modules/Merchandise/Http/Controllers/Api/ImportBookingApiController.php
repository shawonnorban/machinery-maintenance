<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\FabricBooking;
use App\Modules\Merchandise\Models\FabricBookingLine;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\Style;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Fabric Booking → Imported Fabric Booking (AnnotechERP 06/11), as Brickland
 * works it: the confirmed orders, soonest shipment first, with their lead
 * time; and against an order, its import fabric bookings — raised from a
 * dialog (booking date, L/C reference, SP#, ship date, remarks), numbered
 * BK-IMP-00001, approved with one press (and closed from then on) or
 * removed while still a draft. Their fabric lines are added on the Booking
 * Items panel of the Additional and Buffer booking screens.
 */
class ImportBookingApiController extends ApiController
{
    /** The orders an import booking is raised against. */
    private const OPEN = ['CONFIRMED', 'IN_PRODUCTION'];

    /** Records: the confirmed orders, soonest shipment first, with how many import bookings each has. */
    public function orders(): JsonResponse
    {
        $this->allow('merchandise.booking.view');
        $orders = Order::query()->whereIn('status', self::OPEN)->orderBy('ship_date')->orderBy('order_no')->get();
        $styles = Style::query()->whereIn('id', $orders->pluck('style_id')->filter())->get()->keyBy('id');
        $buyers = Buyer::query()->whereIn('id', $orders->pluck('buyer_id')->merge($styles->pluck('buyer_id'))->filter())->get(['id', 'name', 'short_name', 'code'])->keyBy('id');
        $counts = FabricBooking::query()->where('booking_type', 'IMPORT')->whereIn('order_id', $orders->pluck('id'))
            ->select('order_id', DB::raw('count(*) as n'))->groupBy('order_id')->pluck('n', 'order_id');

        return ApiResponse::ok($orders->map(function (Order $o) use ($styles, $buyers, $counts): array {
            $s = $o->style_id ? ($styles[$o->style_id] ?? null) : null;
            $b = $buyers[$o->buyer_id ?? $s?->buyer_id] ?? null;

            return [
                'id' => $o->id, 'order_no' => $o->order_no, 'buyer' => $b ? ($b->short_name ?: $b->name) : null, 'style_desc' => $s?->description,
                'style' => $s?->styleNo() ?? $o->style_no, 'order_type' => $o->order_type, 'order_qty' => (float) $o->total_qty,
                'conf_date' => $o->conf_date?->toDateString(), 'ship_date' => $o->ship_date?->toDateString(),
                // Brickland's Lead Time: the days from confirmation to shipment.
                'lead_time' => $o->conf_date && $o->ship_date ? (int) $o->conf_date->startOfDay()->diffInDays($o->ship_date->startOfDay(), false) : null,
                'bookings' => (int) ($counts[$o->id] ?? 0),
            ];
        })->values()->all());
    }

    /** One order's import fabric bookings. */
    public function index(Request $request): JsonResponse
    {
        $this->allow('merchandise.booking.view');
        $data = $request->validate(['order_id' => ['required', Rule::exists('merch_orders', 'id')->where('company_id', $this->caller()->companyId)]]);

        return ApiResponse::ok($this->bookings($data['order_id']));
    }

    /** Raise booking: kept at once against the order. */
    public function store(Request $request): JsonResponse
    {
        $this->allow('merchandise.booking.manage');
        $data = $request->validate([
            'order_id' => ['required', Rule::exists('merch_orders', 'id')->where('company_id', $this->caller()->companyId)],
            'booking_date' => ['required', 'date'],
            'ref_no' => ['nullable', 'string', 'max:64'],
            'sp_no' => ['nullable', 'string', 'max:32'],
            'ship_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ], ['booking_date.required' => __('merchandise.requisition.date_required')]);
        $order = Order::query()->whereKey($data['order_id'])->firstOrFail();
        if (! in_array($order->status, self::OPEN, true)) {
            throw ValidationException::withMessages(['order_id' => __('merchandise.import_booking.order_closed')]);
        }
        $style = $order->style_id ? Style::query()->whereKey($order->style_id)->first(['buyer_office_id']) : null;
        $blank = fn (string $k): ?string => isset($data[$k]) && trim((string) $data[$k]) !== '' ? trim((string) $data[$k]) : null;
        DB::transaction(fn (): FabricBooking => FabricBooking::query()->create([
            'booking_type' => 'IMPORT', 'booking_no' => $this->nextNumber(), 'revision' => 0, 'booking_date' => $data['booking_date'],
            'program_by' => $this->caller()->auditUserId(), 'created_by' => $this->caller()->auditUserId(), 'order_id' => $order->id, 'style_id' => $order->style_id,
            'buyer_office_id' => $order->buyer_office_id ?? $style?->buyer_office_id, 'currency' => $order->currency ? strtoupper((string) $order->currency) : 'USD', 'conv_rate' => 1,
            'ref_no' => $blank('ref_no'), 'sp_no' => $blank('sp_no'), 'ship_date' => $data['ship_date'] ?? null, 'remarks' => $blank('remarks'), 'status' => 'DRAFT', 'active' => true,
        ]));

        return ApiResponse::created($this->bookings($order->id));
    }

    /** Approve: one press, and the booking is closed from then on. */
    public function approve(string $booking): JsonResponse
    {
        $this->allow('merchandise.booking.manage');
        $row = $this->import($booking);
        if ($row->status !== 'APPROVED') {
            $row->forceFill(['status' => 'APPROVED', 'approved_at' => now(), 'approved_by' => $this->caller()->auditUserId()])->save();
        }

        return ApiResponse::ok($this->bookings($row->order_id));
    }

    public function destroy(string $booking): JsonResponse
    {
        $this->allow('merchandise.booking.manage');
        $row = $this->import($booking);
        if ($row->isLocked()) {
            throw ValidationException::withMessages(['booking' => __('merchandise.import_booking.approved_locked')]);
        }
        $order = $row->order_id;
        $row->delete();

        return ApiResponse::ok($this->bookings($order));
    }

    private function import(string $booking): FabricBooking
    {
        return FabricBooking::query()->where('booking_no', $booking)->where('booking_type', 'IMPORT')->firstOrFail();
    }

    /** @return list<array<string, mixed>> */
    private function bookings(?string $orderId): array
    {
        $rows = FabricBooking::query()->where('booking_type', 'IMPORT')->where('order_id', $orderId)->orderBy('id')->get();
        $counts = FabricBookingLine::query()->whereIn('booking_id', $rows->pluck('id'))->select('booking_id', DB::raw('count(*) as n'))->groupBy('booking_id')->pluck('n', 'booking_id');

        return $rows->map(fn (FabricBooking $b): array => [
            'booking_no' => $b->booking_no, 'booking_date' => $b->booking_date->toDateString(), 'revision' => $b->revision, 'ref_no' => $b->ref_no, 'sp_no' => $b->sp_no,
            'ship_date' => $b->ship_date?->toDateString(), 'items' => (int) ($counts[$b->id] ?? 0), 'status' => $b->status, 'locked' => $b->isLocked(),
        ])->values()->all();
    }

    /** BK-IMP-00001 …: the company's next import booking; held to the end of the save. */
    private function nextNumber(): string
    {
        $head = FabricBooking::PREFIXES['IMPORT'];
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['merch_fabric_bookings:IMPORT:'.app(TenantContext::class)->companyId()]);
        $n = 1 + (int) FabricBooking::query()->where('booking_type', 'IMPORT')->pluck('booking_no')->map(fn (string $v): int => (int) substr($v, strlen($head)))->max();

        return $head.str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }
}
