<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Http\Controllers\Api;

use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\ItemMaster\Models\ItemDepartment;
use App\Modules\Procurement\Actions\ManageFabricBooking;
use App\Modules\Procurement\Models\FabricBooking;
use App\Modules\Procurement\Support\Currencies;
use App\Modules\Procurement\Models\FabricBookingLine;
use App\Modules\Production\Models\Style;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Vendor\Models\Vendor;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Fabric bookings over the wire (Procurement spec §3).
 *
 * Confirming returns the over-booking warnings alongside the booking rather
 * than refusing: the screen shows them, the merchandiser decides.
 */
class FabricBookingApiController extends ApiController
{
    public const VIEW = 'procurement.booking.view_any';

    public const MANAGE = 'procurement.booking.manage';

    private const FILTERS = ['status', 'factory_id', 'vendor_id', 'style_id', 'production_order_id'];

    private const SORTS = ['booking_no', 'booking_date', 'required_date', 'status', 'created_at'];

    private const RELATIONS = [
        'factory:id,name', 'vendor:id,name,code', 'creator:id,name',
        'productionOrder:id,order_no,buyer', 'style:id,style_no',
        'purchaseRequisition:id,pr_no,status', 'lines.uom:id,code,name', 'lines.reservation',
    ];

    public function __construct(
        private readonly TenantContext $context,
        private readonly ManageFabricBooking $bookings,
    ) {}

    /**
     * Its own options rather than the purchase order's: somebody who may
     * book fabric does not necessarily hold `procurement.order.create`, and
     * borrowing that endpoint would 403 them out of their own screen.
     */
    public function formOptions(): JsonResponse
    {
        $this->allow(self::MANAGE);

        $factoryIds = $this->context->accessibleFactoryIds();
        $companyId = $this->context->companyId();

        return ApiResponse::ok([
            'factories' => Factory::whereIn('id', $factoryIds)->orderBy('name')->get(['id', 'name'])->all(),
            'vendors' => Vendor::query()->where('status', 'ACTIVE')->whereIn('vendor_type', ['SUPPLIER', 'BOTH'])
                ->orderBy('name')->get(['id', 'name', 'code', 'default_currency'])->all(),
            'item_departments' => ItemDepartment::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->all(),
            'uoms' => UnitOfMeasure::availableTo($companyId)->where('active', true)->orderBy('code')->get(['id', 'code', 'name'])->all(),
            // Styles on orders still open — what a booking can be measured
            // against. A booking without one is allowed and simply says
            // nothing about over-booking.
            'styles' => Style::query()
                ->whereHas('order', fn ($q) => $q->whereIn('status', ['CONFIRMED', 'IN_PROGRESS'])->whereIn('factory_id', $factoryIds))
                ->with('order:id,order_no,buyer')
                ->orderBy('style_no')
                ->get()
                ->map(fn (Style $style): array => [
                    'id' => $style->id,
                    'label' => trim(($style->order?->order_no ?? '').' · '.$style->style_no),
                    'order_id' => $style->production_order_id,
                ])->all(),
            'currencies' => Currencies::all(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->allow(self::VIEW);

        $query = FabricBooking::query()
            ->with(['factory:id,name', 'vendor:id,name,code', 'style:id,style_no'])
            ->withCount('lines')
            ->whereIn('factory_id', $this->context->accessibleFactoryIds());

        if (is_string($search = $request->query('search')) && $search !== '') {
            $query->whereRaw('LOWER(booking_no) LIKE ?', ['%'.mb_strtolower($search).'%']);
        }

        $query = $this->applyFilters($query, $request, self::FILTERS);
        $query = $this->applySort($query, $request, self::SORTS, 'created_at');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (FabricBooking $booking): array => $this->summary($booking),
        );
    }

    public function show(FabricBooking $booking): JsonResponse
    {
        $this->allow(self::VIEW);
        $this->assertReachable($booking);

        return ApiResponse::ok($this->detail($booking));
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow(self::MANAGE);

        $data = $this->validated($request, creating: true);

        if (! $this->context->canAccessFactory($data['factory_id'])) {
            abort(403);
        }

        return ApiResponse::created($this->detail($this->bookings->create(
            Factory::findOrFail($data['factory_id']), $data, $data['lines'], $this->caller()->auditUserId(),
        )));
    }

    public function update(Request $request, FabricBooking $booking): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachable($booking);

        $data = $this->validated($request, creating: false);

        return ApiResponse::ok($this->detail($this->bookings->update($booking, $data, $data['lines'])));
    }

    public function confirm(FabricBooking $booking): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachable($booking);

        ['booking' => $confirmed, 'warnings' => $warnings] = $this->bookings->confirm(
            $booking, $this->caller()->auditUserId(),
        );

        return ApiResponse::ok($this->detail($confirmed) + ['warnings' => $warnings]);
    }

    public function cancel(Request $request, FabricBooking $booking): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachable($booking);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return ApiResponse::ok($this->detail($this->bookings->cancel(
            $booking, $data['reason'] ?? null, $this->caller()->auditUserId(),
        )));
    }

    public function convert(FabricBooking $booking): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachable($booking);

        $requisition = $this->bookings->convertToRequisition($booking, $this->caller()->auditUserId());

        return ApiResponse::ok($this->detail($booking->fresh()) + [
            'purchase_requisition' => ['id' => $requisition->id, 'pr_no' => $requisition->pr_no, 'status' => $requisition->status],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'factory_id' => [$creating ? 'required' : 'sometimes', 'string', 'size:26'],
            'vendor_id' => [$creating ? 'required' : 'sometimes', 'string', 'size:26'],
            'production_order_id' => ['nullable', 'string', 'size:26'],
            'style_id' => ['nullable', 'string', 'size:26'],
            'booking_date' => ['nullable', 'date'],
            'required_date' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'string', 'size:26'],
            'lines.*.uom_id' => ['nullable', 'string', 'size:26'],
            'lines.*.booked_qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.tentative_rate' => ['nullable', 'numeric', 'min:0'],
            'lines.*.required_date' => ['nullable', 'date'],
            'lines.*.notes' => ['nullable', 'string', 'max:500'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(FabricBooking $booking): array
    {
        return [
            'id' => $booking->id,
            'booking_no' => $booking->booking_no,
            'booking_date' => $booking->booking_date?->toDateString(),
            'required_date' => $booking->required_date?->toDateString(),
            'status' => $booking->status,
            'currency' => $booking->currency,
            'factory' => $booking->factory === null ? null : ['id' => $booking->factory->id, 'name' => $booking->factory->name],
            'vendor' => $booking->vendor === null ? null : ['id' => $booking->vendor->id, 'name' => $booking->vendor->name, 'code' => $booking->vendor->code],
            'style' => $booking->style === null ? null : ['id' => $booking->style->id, 'style_no' => $booking->style->style_no],
            'line_count' => $booking->lines_count ?? ($booking->relationLoaded('lines') ? $booking->lines->count() : null),
            'created_at' => $booking->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(FabricBooking $booking): array
    {
        $booking->loadMissing(self::RELATIONS);

        $mayManage = $this->caller()->can(self::MANAGE);

        return $this->summary($booking) + [
            'notes' => $booking->notes,
            'production_order' => $booking->productionOrder === null ? null : [
                'id' => $booking->productionOrder->id,
                'order_no' => $booking->productionOrder->order_no,
                'buyer' => $booking->productionOrder->buyer,
            ],
            'purchase_requisition' => $booking->purchaseRequisition === null ? null : [
                'id' => $booking->purchaseRequisition->id,
                'pr_no' => $booking->purchaseRequisition->pr_no,
                'status' => $booking->purchaseRequisition->status,
            ],
            'created_by' => $booking->creator === null ? null : ['id' => $booking->creator->id, 'name' => $booking->creator->name],
            'confirmed_at' => $booking->confirmed_at?->toIso8601String(),
            'cancelled_at' => $booking->cancelled_at?->toIso8601String(),
            'converted_at' => $booking->converted_at?->toIso8601String(),
            'can_edit' => $booking->isEditable() && $mayManage,
            'can_confirm' => $booking->canTransitionTo('CONFIRMED') && $mayManage,
            'can_convert' => $booking->canTransitionTo('CONVERTED') && $mayManage,
            'can_cancel' => $booking->canTransitionTo('CANCELLED') && $mayManage,
            'lines' => $booking->lines->map(fn (FabricBookingLine $line): array => [
                'id' => $line->id,
                'item_id' => $line->item_id,
                'item_code' => $line->item_code,
                'item_name' => $line->item_name,
                'uom' => $line->uom === null ? null : ['id' => $line->uom->id, 'code' => $line->uom->code, 'name' => $line->uom->name],
                'booked_qty' => (string) $line->booked_qty,
                'tentative_rate' => $line->tentative_rate === null ? null : (string) $line->tentative_rate,
                'line_total' => $line->lineTotal(),
                'required_date' => $line->required_date?->toDateString(),
                'reservation_status' => $line->reservation?->status,
                'notes' => $line->notes,
            ])->all(),
        ];
    }

    private function assertReachable(FabricBooking $booking): void
    {
        if (! $this->context->canAccessFactory((string) $booking->factory_id)) {
            abort(404);
        }
    }
}
