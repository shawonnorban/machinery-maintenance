<?php

declare(strict_types=1);

namespace App\Modules\Costing\Http\Controllers\Api;

use App\Modules\Asset\Models\Asset;
use App\Modules\Costing\Models\CostCategory;
use App\Modules\Costing\Models\CostEntry;
use App\Modules\Costing\Services\AssetLifecycleCost;
use App\Modules\Costing\Services\CostPoster;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * What a machine has cost, over the wire (API 15; mirrors the web
 * `AssetCostController`, which this delegates every write to unchanged per
 * ADR-003).
 */
class CostEntryApiController extends ApiController
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * The categories the post-a-cost form's dropdown needs — its own lookup
     * rather than `masterdata.manage`-gated `GET /settings/master-data/...`,
     * because MAINTENANCE_MANAGER holds `cost.entry.create` a tier below
     * `masterdata.manage` (`RoleSeeder`: the permission only arrives at
     * `factoryManager`, which extends it) — the same gap Asset/Inventory/
     * WorkOrder/Teams each hit before this.
     */
    public function categories(): JsonResponse
    {
        $this->allow('cost.entry.view');

        $categories = CostCategory::availableTo($this->context->companyId())
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return ApiResponse::ok($categories->all());
    }

    public function index(Request $request): JsonResponse
    {
        $this->allow('cost.entry.view');

        $query = CostEntry::query()->with(['category:id,name', 'workOrder:id,work_order_number']);

        $query = $this->applyFilters($query, $request, ['asset_id', 'work_order_id', 'cost_category_id', 'source_type']);
        $query = $this->applySort($query, $request, ['occurred_at', 'posted_at'], 'occurred_at', 'desc');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (CostEntry $entry): array => $this->summary($entry),
        );
    }

    public function store(Request $request, CostPoster $costs): JsonResponse
    {
        $this->allow('cost.entry.create');

        $data = $request->validate([
            'asset_id' => ['required', 'string', 'size:26'],
            'cost_category_id' => ['required', 'string', 'size:26'],
            'amount' => ['required', 'numeric', 'not_in:0'],
            'currency' => ['required', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            // Labour and parts are derived from the work order itself; a
            // client does not offer them and the poster refuses them.
            'source_type' => ['required', Rule::in(['EXTERNAL_SERVICE', 'VENDOR', 'TRANSPORT', 'MANUAL'])],
            'occurred_at' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'invoice_reference' => ['nullable', 'string', 'max:255'],
        ]);

        $entry = $costs->post([
            'asset_id' => $data['asset_id'],
            'cost_category_id' => $data['cost_category_id'],
            'amount' => (string) $data['amount'],
            'currency' => strtoupper($data['currency']),
            'exchange_rate' => (string) ($data['exchange_rate'] ?? '1'),
            'source_type' => $data['source_type'],
            'occurred_at' => $data['occurred_at'] ?? now(),
            'description' => $data['description'] ?? null,
            'invoice_reference' => $data['invoice_reference'] ?? null,
        ], $this->caller()->auditUserId());

        return ApiResponse::created($this->summary($entry));
    }

    /**
     * Its own permission, not create: undoing a posted cost changes a figure
     * somebody has already reported.
     */
    public function reverse(Request $request, CostEntry $entry, CostPoster $costs): JsonResponse
    {
        $this->allow('cost.entry.reverse');

        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $reversal = $costs->reverse($entry, $this->caller()->auditUserId(), $data['reason']);

        return ApiResponse::created($this->summary($reversal));
    }

    public function lifecycleCost(Asset $asset, AssetLifecycleCost $lifecycle): JsonResponse
    {
        $this->allow('cost.entry.view');

        $summary = $lifecycle->forAsset($asset);

        return ApiResponse::ok([
            'asset_id' => $asset->id,
            'acquisition' => $summary['acquisition'],
            'maintenance' => $summary['maintenance'],
            'repair' => $summary['repair'],
            'other' => $summary['other'],
            'total_spend' => $summary['total_spend'],
            'lifetime_total' => $summary['lifetime_total'],
            'entry_count' => $summary['entry_count'],
            'currency' => $summary['currency'],
            'spend_against_value_percent' => $lifecycle->spendAgainstValue($asset),
            // The web reads this off `$request->user()->can(...)` directly
            // in the controller to decide whether to render the post-cost
            // form at all; a client has no such local check to make, so it
            // travels in the response instead — same reasoning as
            // Approval's `can_act`.
            'can_post' => $this->caller()->can('cost.entry.create'),
            'can_reverse' => $this->caller()->can('cost.entry.reverse'),
            'by_category' => collect($summary['by_category'])->map(fn ($row): array => [
                'cost_category_id' => $row->cost_category_id,
                'category' => $row->category?->name,
                'total' => $row->total,
                'entries' => $row->entries,
            ])->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(CostEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'asset_id' => $entry->asset_id,
            'work_order' => $entry->workOrder === null ? null : [
                'id' => $entry->workOrder->id, 'work_order_number' => $entry->workOrder->work_order_number,
            ],
            'category' => $entry->category?->name,
            'cost_category_id' => $entry->cost_category_id,
            'amount' => $entry->amount,
            'currency' => $entry->currency,
            'exchange_rate' => $entry->exchange_rate,
            'base_amount' => $entry->base_amount,
            'source_type' => $entry->source_type,
            'is_reversal' => $entry->is_reversal,
            'reverses_cost_entry_id' => $entry->reverses_cost_entry_id,
            'description' => $entry->description,
            'invoice_reference' => $entry->invoice_reference,
            'occurred_at' => $entry->occurred_at?->toIso8601String(),
            'posted_at' => $entry->posted_at?->toIso8601String(),
        ];
    }
}
