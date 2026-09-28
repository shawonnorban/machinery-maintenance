<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Actions;

use App\Modules\ItemMaster\Actions\ReserveItemStock;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Services\ItemLineResolver;
use App\Modules\Procurement\Models\FabricBooking;
use App\Modules\Procurement\Models\FabricBookingLine;
use App\Modules\Procurement\Models\PurchaseRequisition;
use App\Modules\Production\Models\Order;
use App\Modules\Production\Models\Style;
use App\Modules\Production\Models\StyleBomLine;
use App\Modules\Settings\Services\NumberSequenceGenerator;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Vendor\Models\Vendor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Booking fabric with a mill before there is a purchase order
 * (Procurement spec §3).
 *
 * Confirming is the moment that matters. A confirmed booking is a promise
 * the factory plans against, so it does two things at once: it stakes a
 * claim on the incoming stock (Inventory spec §7.1) so the yardage is not
 * quietly counted twice, and it makes the booked quantity visible to the
 * material requirement, which subtracts it from what still needs buying.
 *
 * Over-booking is a warning, never a refusal. A merchandiser books against
 * a buyer's forecast that moves week to week; a system that blocks the
 * booking because it exceeds today's bill of materials is a system people
 * work around.
 */
class ManageFabricBooking
{
    private const SCALE = 4;

    public function __construct(
        private readonly NumberSequenceGenerator $numbers,
        private readonly ItemLineResolver $lines,
        private readonly ReserveItemStock $reservations,
        private readonly ManagePurchaseRequisition $purchaseRequisitions,
    ) {}

    /**
     * @param  array<string, mixed>  $data  vendor_id, production_order_id?, style_id?, booking_date?, required_date?, currency?, notes?
     * @param  list<array<string, mixed>>  $lines  item_id, booked_qty, uom_id?, tentative_rate?, required_date?, notes?
     */
    public function create(Factory $factory, array $data, array $lines, ?string $userId = null): FabricBooking
    {
        $resolved = $this->lines->resolve($lines, 'booked_qty');
        $this->assertVendor($data['vendor_id'] ?? null);
        $style = $this->resolveStyle($data);

        return DB::transaction(function () use ($factory, $data, $resolved, $style, $userId): FabricBooking {
            $booking = FabricBooking::create([
                'factory_id' => $factory->id,
                'booking_no' => $this->numbers->next('FABRIC_BOOKING', $factory),
                'booking_date' => $data['booking_date'] ?? CarbonImmutable::today(),
                'vendor_id' => $data['vendor_id'],
                'production_order_id' => $style?->production_order_id ?? ($data['production_order_id'] ?? null),
                'style_id' => $style?->id,
                'required_date' => $data['required_date'] ?? null,
                'status' => 'DRAFT',
                'currency' => $data['currency'] ?? 'BDT',
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            $this->writeLines($booking, $resolved);

            return $booking->fresh();
        });
    }

    /**
     * A draft is replaced wholesale — the same rule the purchase requisition
     * follows, and for the same reason: a line edited in place keeps numbers
     * from an earlier day.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $lines
     */
    public function update(FabricBooking $booking, array $data, array $lines): FabricBooking
    {
        $this->assertEditable($booking);

        $resolved = $this->lines->resolve($lines, 'booked_qty');

        if (array_key_exists('vendor_id', $data)) {
            $this->assertVendor($data['vendor_id']);
        }

        $style = $this->resolveStyle($data);

        return DB::transaction(function () use ($booking, $data, $resolved, $style): FabricBooking {
            $booking->forceFill([
                'vendor_id' => $data['vendor_id'] ?? $booking->vendor_id,
                'booking_date' => $data['booking_date'] ?? $booking->booking_date,
                'required_date' => array_key_exists('required_date', $data) ? $data['required_date'] : $booking->required_date,
                'production_order_id' => $style?->production_order_id ?? ($data['production_order_id'] ?? $booking->production_order_id),
                'style_id' => $style?->id ?? $booking->style_id,
                'currency' => $data['currency'] ?? $booking->currency,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $booking->notes,
            ])->save();

            $booking->lines()->delete();
            $this->writeLines($booking, $resolved);

            return $booking->fresh();
        });
    }

    /**
     * Confirms the booking and stakes its claim.
     *
     * The claim is an earmark rather than a reservation: the fabric is at
     * the mill, not on a shelf, so there is nothing to encumber yet. When
     * it eventually arrives through a goods receipt, `fillPendingEarmarks`
     * turns it into a real hold before anybody else can draw it.
     *
     * @return array{booking: FabricBooking, warnings: list<array<string, string>>}
     */
    public function confirm(FabricBooking $booking, ?string $userId = null): array
    {
        $this->assertTransition($booking, 'CONFIRMED');

        $booking->load('lines');

        if ($booking->lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('procurement.needs_lines')]);
        }

        $this->lines->assertAllActive($booking->lines);

        $warnings = $this->overBookingWarnings($booking);

        DB::transaction(function () use ($booking, $userId): void {
            foreach ($booking->lines as $line) {
                $claim = $this->reservations->earmark(
                    Item::findOrFail($line->item_id),
                    (string) $line->booked_qty,
                    [
                        'reservation_type' => 'BOOKING',
                        'reference_type' => 'fabric_booking_line',
                        'reference_id' => $line->id,
                        'reserved_by' => $userId,
                    ],
                );

                $line->forceFill(['item_reservation_id' => $claim->id])->save();
            }

            $booking->forceFill([
                'status' => 'CONFIRMED',
                'confirmed_at' => CarbonImmutable::now(),
            ])->save();
        });

        return ['booking' => $booking->fresh(), 'warnings' => $warnings];
    }

    /**
     * Cancelling gives the claim back. A booking nobody released would keep
     * its yardage out of the requirement for ever, and the factory would
     * under-buy without anything looking wrong.
     */
    public function cancel(FabricBooking $booking, ?string $reason = null, ?string $userId = null): FabricBooking
    {
        $this->assertTransition($booking, 'CANCELLED');

        return DB::transaction(function () use ($booking, $reason, $userId): FabricBooking {
            $this->releaseClaims($booking, $userId, 'CANCELLED');

            $booking->forceFill([
                'status' => 'CANCELLED',
                'cancelled_at' => CarbonImmutable::now(),
                'notes' => $reason === null ? $booking->notes : trim(($booking->notes ?? '')."\n".$reason),
            ])->save();

            return $booking->fresh();
        });
    }

    /**
     * Turns a confirmed booking into a draft purchase requisition — the
     * "Booking থেকে সরাসরি PR/PO" of §3.
     *
     * The claim stays keyed to the booking line rather than being re-pointed
     * at the requisition. The spec's "ref আপডেট" is about not losing the
     * thread, and the thread is intact: the booking line records the
     * requisition it produced, so both directions are traceable, while
     * everything that releases or fills a claim keeps working off one key.
     */
    public function convertToRequisition(FabricBooking $booking, ?string $userId = null): PurchaseRequisition
    {
        $this->assertTransition($booking, 'CONVERTED');

        $booking->load('lines');

        return DB::transaction(function () use ($booking, $userId): PurchaseRequisition {
            $requisition = $this->purchaseRequisitions->create(
                Factory::findOrFail($booking->factory_id),
                [
                    'pr_type' => 'AUTO',
                    'source_reference' => $booking->id,
                    'currency' => $booking->currency,
                    'notes' => __('procurement.booking_pr_notes', ['number' => $booking->booking_no]),
                ],
                $booking->lines->map(fn (FabricBookingLine $line): array => [
                    'item_id' => $line->item_id,
                    'requested_qty' => (string) $line->booked_qty,
                    'uom_id' => $line->uom_id,
                    'estimated_rate' => $line->tentative_rate,
                    'required_date' => $line->required_date?->toDateString(),
                ])->all(),
                $userId,
            );

            $booking->forceFill([
                'status' => 'CONVERTED',
                'converted_at' => CarbonImmutable::now(),
                'converted_pr_id' => $requisition->id,
            ])->save();

            return $requisition;
        });
    }

    /**
     * What this style has booked against what its bill of materials says it
     * needs. Returned, not thrown: see the class note.
     *
     * @return list<array<string, string>>
     */
    private function overBookingWarnings(FabricBooking $booking): array
    {
        if ($booking->style_id === null) {
            // Nothing to measure against — a booking made before the style
            // exists is normal early in a season.
            return [];
        }

        $style = Style::find($booking->style_id);

        if ($style === null) {
            return [];
        }

        $bom = StyleBomLine::where('style_id', $style->id)->get()->keyBy('item_id');
        $alreadyBooked = $this->bookedByItem($style->id, $booking->id);
        $warnings = [];

        foreach ($booking->lines as $line) {
            /** @var StyleBomLine|null $bomLine */
            $bomLine = $bom->get($line->item_id);

            if ($bomLine === null) {
                $warnings[] = [
                    'item' => $line->item_name,
                    'message' => __('procurement.booking_item_not_in_bom', [
                        'item' => $line->item_name, 'style' => $style->style_no,
                    ]),
                ];

                continue;
            }

            $required = $bomLine->requiredFor((string) $style->quantity);
            $total = bcadd($alreadyBooked[$line->item_id] ?? '0.0000', (string) $line->booked_qty, self::SCALE);

            if (bccomp($total, $required, self::SCALE) > 0) {
                $warnings[] = [
                    'item' => $line->item_name,
                    'message' => __('procurement.over_booking', [
                        'item' => $line->item_name,
                        'booked' => $this->trim($total),
                        'required' => $this->trim($required),
                    ]),
                ];
            }
        }

        return $warnings;
    }

    /**
     * @return array<string, string> item id => quantity booked on other live bookings
     */
    private function bookedByItem(string $styleId, string $exceptBookingId): array
    {
        $lines = FabricBookingLine::whereHas(
            'booking',
            fn ($q) => $q->where('style_id', $styleId)
                ->whereIn('status', ['CONFIRMED', 'CONVERTED'])
                ->whereKeyNot($exceptBookingId),
        )->get(['item_id', 'booked_qty']);

        $totals = [];

        foreach ($lines as $line) {
            $totals[$line->item_id] = bcadd($totals[$line->item_id] ?? '0.0000', (string) $line->booked_qty, self::SCALE);
        }

        return $totals;
    }

    private function releaseClaims(FabricBooking $booking, ?string $userId, string $status): void
    {
        foreach ($booking->lines()->get() as $line) {
            $this->reservations->releaseFor('fabric_booking_line', $line->id, $userId, $status);
        }
    }

    /**
     * @param  Collection<int, array{item: Item, uom_id: string, quantity: string, input: array<string, mixed>}>  $resolved
     */
    private function writeLines(FabricBooking $booking, Collection $resolved): void
    {
        foreach ($resolved as $line) {
            /** @var Item $item */
            $item = $line['item'];
            $input = $line['input'];

            FabricBookingLine::create([
                'fabric_booking_id' => $booking->id,
                'item_id' => $item->id,
                'item_code' => $item->item_code,
                'item_name' => $item->name,
                'uom_id' => $line['uom_id'],
                'booked_qty' => $line['quantity'],
                'tentative_rate' => isset($input['tentative_rate']) && $input['tentative_rate'] !== '' && $input['tentative_rate'] !== null
                    ? ItemLineResolver::money($input['tentative_rate'])
                    : null,
                'required_date' => $input['required_date'] ?? $booking->required_date,
                'notes' => $input['notes'] ?? null,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveStyle(array $data): ?Style
    {
        $styleId = $data['style_id'] ?? null;

        if ($styleId === null || $styleId === '') {
            return null;
        }

        $style = Style::find($styleId);

        if ($style === null) {
            throw ValidationException::withMessages(['style_id' => __('production.unknown_style')]);
        }

        $order = Order::find($style->production_order_id);

        if ($order === null || ! $order->isOpen()) {
            throw ValidationException::withMessages([
                'style_id' => __('production.order_not_open', ['status' => $order?->status ?? '—']),
            ])->status(409);
        }

        return $style;
    }

    private function assertVendor(?string $vendorId): void
    {
        if ($vendorId === null || Vendor::where('status', 'ACTIVE')->find($vendorId) === null) {
            throw ValidationException::withMessages(['vendor_id' => __('itemmaster.unknown_vendor')]);
        }
    }

    private function assertEditable(FabricBooking $booking): void
    {
        if (! $booking->isEditable()) {
            throw ValidationException::withMessages([
                'status' => __('procurement.only_draft_editable', ['status' => $booking->status]),
            ])->status(409);
        }
    }

    private function assertTransition(FabricBooking $booking, string $to): void
    {
        if (! $booking->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => __('procurement.invalid_transition', ['from' => $booking->status, 'to' => $to]),
            ])->status(409);
        }
    }

    private function trim(string $value): string
    {
        return rtrim(rtrim($value, '0'), '.');
    }
}
