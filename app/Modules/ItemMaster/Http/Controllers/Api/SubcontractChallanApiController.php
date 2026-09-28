<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Http\Controllers\Api;

use App\Modules\ItemMaster\Actions\ManageSubcontractChallan;
use App\Modules\ItemMaster\Models\SubcontractChallan;
use App\Modules\ItemMaster\Models\SubcontractChallanLine;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Fabric out for dyeing, printing or embroidery (Inventory spec §8).
 *
 * Nothing here posts to the stock ledger: the cloth is still the factory's,
 * it is simply not on a shelf. The roll's own status carries that.
 */
class SubcontractChallanApiController extends ApiController
{
    public const VIEW = 'itemmaster.subcontract.view_any';

    public const MANAGE = 'itemmaster.subcontract.manage';

    private const FILTERS = ['status', 'factory_id', 'vendor_id', 'purpose'];

    private const SORTS = ['challan_no', 'challan_date', 'expected_return_date', 'status', 'created_at'];

    public function __construct(
        private readonly TenantContext $context,
        private readonly ManageSubcontractChallan $challans,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->allow(self::VIEW);

        $query = SubcontractChallan::query()
            ->with(['factory:id,name', 'vendor:id,name,code'])
            ->withCount('lines')
            ->whereIn('factory_id', $this->context->accessibleFactoryIds());

        if (is_string($search = $request->query('search')) && $search !== '') {
            $query->whereRaw('LOWER(challan_no) LIKE ?', ['%'.mb_strtolower($search).'%']);
        }

        // The question a store actually asks: what is still out there.
        if ($request->boolean('outstanding')) {
            $query->whereIn('status', ['SENT', 'PARTIALLY_RETURNED']);
        }

        $query = $this->applyFilters($query, $request, self::FILTERS);
        $query = $this->applySort($query, $request, self::SORTS, 'challan_date');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (SubcontractChallan $challan): array => $this->summary($challan),
        );
    }

    public function show(SubcontractChallan $challan): JsonResponse
    {
        $this->allow(self::VIEW);
        $this->assertReachable($challan);

        return ApiResponse::ok($this->detail($challan));
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow(self::MANAGE);

        $data = $request->validate([
            'factory_id' => ['required', 'string', 'size:26'],
            'vendor_id' => ['required', 'string', 'size:26'],
            'purpose' => ['required', Rule::in(SubcontractChallan::PURPOSES)],
            'challan_date' => ['nullable', 'date'],
            'expected_return_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'roll_ids' => ['required', 'array', 'min:1'],
            'roll_ids.*' => ['string', 'size:26'],
        ]);

        if (! $this->context->canAccessFactory($data['factory_id'])) {
            abort(403);
        }

        return ApiResponse::created($this->detail($this->challans->create(
            Factory::findOrFail($data['factory_id']), $data, $data['roll_ids'], $this->caller()->auditUserId(),
        )));
    }

    public function send(SubcontractChallan $challan): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachable($challan);

        return ApiResponse::ok($this->detail($this->challans->send($challan, $this->caller()->auditUserId())));
    }

    public function receiveReturn(Request $request, SubcontractChallan $challan): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachable($challan);

        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.line_id' => ['required', 'string', 'size:26'],
            'lines.*.returned_qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.wastage_qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.quality_status' => ['nullable', 'string', 'max:64'],
            'lines.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        return ApiResponse::ok($this->detail($this->challans->receiveReturn(
            $challan, $data['lines'], $this->caller()->auditUserId(),
        )));
    }

    public function close(Request $request, SubcontractChallan $challan): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachable($challan);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return ApiResponse::ok($this->detail($this->challans->close(
            $challan, $data['reason'] ?? null, $this->caller()->auditUserId(),
        )));
    }

    public function cancel(Request $request, SubcontractChallan $challan): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachable($challan);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return ApiResponse::ok($this->detail($this->challans->cancel($challan, $data['reason'] ?? null)));
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(SubcontractChallan $challan): array
    {
        return [
            'id' => $challan->id,
            'challan_no' => $challan->challan_no,
            'challan_date' => $challan->challan_date?->toDateString(),
            'purpose' => $challan->purpose,
            'status' => $challan->status,
            'expected_return_date' => $challan->expected_return_date?->toDateString(),
            'overdue' => $challan->isOverdue(),
            'factory' => $challan->factory === null ? null : ['id' => $challan->factory->id, 'name' => $challan->factory->name],
            'vendor' => $challan->vendor === null ? null : ['id' => $challan->vendor->id, 'name' => $challan->vendor->name, 'code' => $challan->vendor->code],
            'line_count' => $challan->lines_count ?? ($challan->relationLoaded('lines') ? $challan->lines->count() : null),
            'created_at' => $challan->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(SubcontractChallan $challan): array
    {
        $challan->loadMissing(['factory:id,name', 'vendor:id,name,code', 'creator:id,name', 'lines.roll:id,roll_no,status']);

        $mayManage = $this->caller()->can(self::MANAGE);

        return $this->summary($challan) + [
            'notes' => $challan->notes,
            'created_by' => $challan->creator === null ? null : ['id' => $challan->creator->id, 'name' => $challan->creator->name],
            'sent_at' => $challan->sent_at?->toIso8601String(),
            'closed_at' => $challan->closed_at?->toIso8601String(),
            'cancelled_at' => $challan->cancelled_at?->toIso8601String(),
            'can_send' => $challan->canTransitionTo('SENT') && $mayManage,
            'can_receive' => $challan->isOut() && $mayManage,
            'can_close' => $challan->canTransitionTo('CLOSED') && $mayManage,
            'can_cancel' => $challan->canTransitionTo('CANCELLED') && $mayManage,
            'lines' => $challan->lines->map(fn (SubcontractChallanLine $line): array => [
                'id' => $line->id,
                'roll_no' => $line->roll_no,
                'roll_status' => $line->roll?->status,
                'fabric_roll_id' => $line->fabric_roll_id,
                'sent_qty' => (string) $line->sent_qty,
                'returned_qty' => (string) $line->returned_qty,
                'wastage_qty' => (string) $line->wastage_qty,
                'outstanding_qty' => $line->outstandingQty(),
                'status' => $line->status,
                'quality_status' => $line->quality_status,
                'returned_at' => $line->returned_at?->toIso8601String(),
                'notes' => $line->notes,
            ])->all(),
        ];
    }

    private function assertReachable(SubcontractChallan $challan): void
    {
        if (! $this->context->canAccessFactory((string) $challan->factory_id)) {
            abort(404);
        }
    }
}
