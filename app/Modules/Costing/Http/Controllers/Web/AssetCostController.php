<?php

declare(strict_types=1);

namespace App\Modules\Costing\Http\Controllers\Web;

use App\Modules\Asset\Models\Asset;
use App\Modules\Costing\Models\CostCategory;
use App\Modules\Costing\Models\CostEntry;
use App\Modules\Costing\Services\AssetLifecycleCost;
use App\Modules\Costing\Services\CostPoster;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Support\TenantTimezone;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AssetCostController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly CostPoster $costs,
        private readonly AssetLifecycleCost $lifecycle,
    ) {}

    public function show(Asset $asset): View
    {
        $this->authorize('cost.entry.view');

        return view('costing::costs.show', [
            'asset' => $asset,
            'summary' => $this->lifecycle->forAsset($asset),
            'spendRatio' => $this->lifecycle->spendAgainstValue($asset),
            'entries' => CostEntry::where('asset_id', $asset->id)
                ->with(['category', 'workOrder:id,work_order_number'])
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->limit(100)
                ->get(),
            'categories' => CostCategory::availableTo($this->context->companyId())
                ->where('active', true)
                ->orderBy('name')
                ->get(),
            'canPost' => request()->user()->can('cost.entry.create'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('cost.entry.create');

        $validated = $request->validate([
            'asset_id' => ['required', 'string', 'size:26'],
            'cost_category_id' => ['required', 'string', 'size:26'],
            'amount' => ['required', 'numeric', 'not_in:0'],
            'currency' => ['required', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            // Labour and parts are derived from the work order itself; the form
            // does not offer them and the action refuses them.
            'source_type' => ['required', Rule::in([
                'EXTERNAL_SERVICE', 'VENDOR', 'TRANSPORT', 'MANUAL',
            ])],
            'occurred_at' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'invoice_reference' => ['nullable', 'string', 'max:255'],
        ]);

        $this->costs->post([
            'asset_id' => $validated['asset_id'],
            'cost_category_id' => $validated['cost_category_id'],
            'amount' => (string) $validated['amount'],
            'currency' => strtoupper($validated['currency']),
            'exchange_rate' => (string) ($validated['exchange_rate'] ?? '1'),
            'source_type' => $validated['source_type'],
            // On the factory's clock, like every other wall-time input: a
            // datetime-local field carries no timezone of its own (SRS 47.2).
            'occurred_at' => filled($validated['occurred_at'] ?? null)
                ? app(TenantTimezone::class)->toUtc((string) $validated['occurred_at'])
                : now(),
            'description' => $validated['description'] ?? null,
            'invoice_reference' => $validated['invoice_reference'] ?? null,
        ], $request->user()->id);

        return back()->with('status', __('cost.posted'));
    }

    public function reverse(Request $request, string $entry): RedirectResponse
    {
        // Its own permission, not create. Undoing a posted cost is the one
        // action here that changes a figure somebody has already reported.
        $this->authorize('cost.entry.reverse');

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $this->costs->reverse(
            CostEntry::findOrFail($entry),
            $request->user()->id,
            $validated['reason'],
        );

        return back()->with('status', __('cost.reversed'));
    }
}
