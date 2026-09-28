<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Http\Controllers\Api;

use App\Modules\Inventory\Models\Bin;
use App\Modules\ItemMaster\Actions\ReserveItemStock;
use App\Modules\ItemMaster\Models\FabricRoll;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemReservation;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Who is holding what, and the manual hold/release a storekeeper needs
 * (Inventory spec §7). Requisition-driven reservations are created by the
 * requisition module; this controller is the view onto all of them plus the
 * one place stock is held by hand.
 */
class ItemReservationApiController extends ApiController
{
    public const VIEW = 'itemmaster.reservation.view_any';

    public const MANAGE = 'itemmaster.reservation.manage';

    private const FILTERS = ['status', 'item_id', 'bin_id', 'reservation_type', 'reference_type', 'reference_id'];

    private const SORTS = ['reserved_at', 'expires_at', 'status', 'created_at'];

    public function __construct(private readonly ReserveItemStock $reservations) {}

    public function index(Request $request): JsonResponse
    {
        $this->allow(self::VIEW);

        $query = ItemReservation::query()
            ->with(['item:id,item_code,name,tracking_mode', 'bin:id,code,name', 'fabricRoll:id,roll_no', 'reserver:id,name']);

        if (! $request->has('status')) {
            // The useful default is what is still tying stock up.
            $query->whereIn('status', ItemReservation::OPEN_STATUSES);
        }

        $query = $this->applyFilters($query, $request, self::FILTERS);
        $query = $this->applySort($query, $request, self::SORTS, 'reserved_at');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (ItemReservation $reservation): array => $this->summary($reservation),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow(self::MANAGE);

        $data = $request->validate([
            'item_id' => ['required', 'string', 'size:26'],
            'bin_id' => ['nullable', 'string', 'size:26'],
            'fabric_roll_id' => ['nullable', 'string', 'size:26'],
            'lot_no' => ['nullable', 'string', 'max:64'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $item = Item::findOrFail($data['item_id']);

        $attributes = [
            'reservation_type' => 'MANUAL',
            'reserved_by' => $this->caller()->auditUserId(),
            'lot_no' => $data['lot_no'] ?? null,
            'expires_at' => isset($data['expires_at']) ? CarbonImmutable::parse($data['expires_at']) : null,
            'override_reason' => $data['notes'] ?? null,
        ];

        // A roll is held by name; everything else by quantity in a bin; and
        // with no bin at all it is a claim on stock still to arrive.
        $reservation = match (true) {
            isset($data['fabric_roll_id']) => $this->reservations->allocateRoll(
                FabricRoll::where('item_id', $item->id)->findOrFail($data['fabric_roll_id']),
                $attributes,
            ),
            isset($data['bin_id']) => $this->reservations->reserve(
                $item,
                Bin::findOrFail($data['bin_id']),
                $this->quantity($data),
                $attributes,
            ),
            default => $this->reservations->earmark($item, $this->quantity($data), $attributes),
        };

        return ApiResponse::created($this->summary($reservation));
    }

    public function release(Request $request, ItemReservation $reservation): JsonResponse
    {
        $this->allow(self::MANAGE);

        $data = $request->validate([
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'status' => ['nullable', Rule::in(['RELEASED', 'CANCELLED'])],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        return ApiResponse::ok($this->summary($this->reservations->release(
            $reservation,
            isset($data['quantity']) ? (string) $data['quantity'] : null,
            $this->caller()->auditUserId(),
            $data['status'] ?? 'RELEASED',
            $data['reason'] ?? null,
        )));
    }

    /**
     * Quantity is optional in the rules because a roll hold does not need
     * one; every other shape of reservation does.
     *
     * @param  array<string, mixed>  $data
     */
    private function quantity(array $data): string
    {
        if (! isset($data['quantity'])) {
            throw ValidationException::withMessages([
                'quantity' => __('validation.required', ['attribute' => 'quantity']),
            ]);
        }

        return (string) $data['quantity'];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(ItemReservation $reservation): array
    {
        $reservation->loadMissing(['item:id,item_code,name,tracking_mode', 'bin:id,code,name', 'fabricRoll:id,roll_no', 'reserver:id,name']);

        return [
            'id' => $reservation->id,
            'item' => $reservation->item === null ? null : [
                'id' => $reservation->item->id,
                'item_code' => $reservation->item->item_code,
                'name' => $reservation->item->name,
            ],
            'bin' => $reservation->bin === null ? null : [
                'id' => $reservation->bin->id,
                'code' => $reservation->bin->code,
                'name' => $reservation->bin->name,
            ],
            'lot_no' => $reservation->lot_no === '' ? null : $reservation->lot_no,
            'fabric_roll' => $reservation->fabricRoll === null ? null : [
                'id' => $reservation->fabricRoll->id,
                'roll_no' => $reservation->fabricRoll->roll_no,
            ],
            'reservation_type' => $reservation->reservation_type,
            'reference_type' => $reservation->reference_type,
            'reference_id' => $reservation->reference_id,
            'quantity' => (string) $reservation->quantity,
            'quantity_issued' => (string) $reservation->quantity_issued,
            'quantity_released' => (string) $reservation->quantity_released,
            'outstanding' => $reservation->outstanding(),
            'status' => $reservation->status,
            'is_earmark' => $reservation->isEarmark(),
            'reserved_by' => $reservation->reserver === null ? null : ['id' => $reservation->reserver->id, 'name' => $reservation->reserver->name],
            'reserved_at' => $reservation->reserved_at?->toIso8601String(),
            'expires_at' => $reservation->expires_at?->toIso8601String(),
            'released_at' => $reservation->released_at?->toIso8601String(),
            'override_reason' => $reservation->override_reason,
        ];
    }
}
