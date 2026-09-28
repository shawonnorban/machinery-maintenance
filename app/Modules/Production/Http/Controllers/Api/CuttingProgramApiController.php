<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Controllers\Api;

use App\Modules\Production\Actions\ManageCuttingProgram;
use App\Modules\Production\Models\CuttingProgram;
use App\Modules\Production\Models\Order;
use App\Modules\Production\Models\Style;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Cutting programmes (Master Data spec §6) — the batch material is issued
 * against and the unit BOM variance is measured on.
 */
class CuttingProgramApiController extends ApiController
{
    private const FILTERS = ['status', 'factory_id', 'style_id'];

    private const SORTS = ['program_no', 'cutting_date', 'status', 'created_at'];

    public function __construct(
        private readonly TenantContext $context,
        private readonly ManageCuttingProgram $programs,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->allow(ProductionOrderApiController::VIEW);

        $query = CuttingProgram::query()
            ->with(['style:id,style_no,production_order_id', 'style.order:id,order_no,buyer', 'factory:id,name'])
            ->whereIn('factory_id', $this->context->accessibleFactoryIds());

        if (is_string($search = $request->query('search')) && $search !== '') {
            $query->whereRaw('LOWER(program_no) LIKE ?', ['%'.mb_strtolower($search).'%']);
        }

        $query = $this->applyFilters($query, $request, self::FILTERS);
        $query = $this->applySort($query, $request, self::SORTS, 'cutting_date');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (CuttingProgram $program): array => $this->summary($program),
        );
    }

    public function show(CuttingProgram $program): JsonResponse
    {
        $this->allow(ProductionOrderApiController::VIEW);
        $this->assertReachable($program);

        return ApiResponse::ok($this->summary($program));
    }

    public function store(Request $request, Style $style): JsonResponse
    {
        $this->allow(ProductionOrderApiController::MANAGE);

        $order = Order::findOrFail($style->production_order_id);

        if (! $this->context->canAccessFactory((string) $order->factory_id)) {
            abort(404);
        }

        $data = $request->validate([
            'program_qty' => ['required', 'numeric', 'gt:0'],
            'cutting_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return ApiResponse::created($this->summary(
            $this->programs->create($style, $data, $this->caller()->auditUserId()),
        ));
    }

    public function update(Request $request, CuttingProgram $program): JsonResponse
    {
        $this->allow(ProductionOrderApiController::MANAGE);
        $this->assertReachable($program);

        $data = $request->validate([
            'program_qty' => ['sometimes', 'numeric', 'gt:0'],
            'cutting_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return ApiResponse::ok($this->summary($this->programs->update($program, $data)));
    }

    public function setStatus(Request $request, CuttingProgram $program): JsonResponse
    {
        $this->allow(ProductionOrderApiController::MANAGE);
        $this->assertReachable($program);

        $data = $request->validate(['status' => ['required', Rule::in(CuttingProgram::STATUSES)]]);

        return ApiResponse::ok($this->summary($this->programs->setStatus($program, $data['status'])));
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(CuttingProgram $program): array
    {
        $program->loadMissing(['style:id,style_no,production_order_id,quantity', 'style.order:id,order_no,buyer', 'factory:id,name']);

        return [
            'id' => $program->id,
            'program_no' => $program->program_no,
            'program_qty' => (string) $program->program_qty,
            'cutting_date' => $program->cutting_date?->toDateString(),
            'status' => $program->status,
            'notes' => $program->notes,
            'factory' => $program->factory === null ? null : ['id' => $program->factory->id, 'name' => $program->factory->name],
            'style' => $program->style === null ? null : [
                'id' => $program->style->id,
                'style_no' => $program->style->style_no,
                'quantity' => (string) $program->style->quantity,
                'order' => $program->style->order === null ? null : [
                    'id' => $program->style->order->id,
                    'order_no' => $program->style->order->order_no,
                    'buyer' => $program->style->order->buyer,
                ],
            ],
            'created_at' => $program->created_at?->toIso8601String(),
        ];
    }

    private function assertReachable(CuttingProgram $program): void
    {
        if (! $this->context->canAccessFactory((string) $program->factory_id)) {
            abort(404);
        }
    }
}
