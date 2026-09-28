<?php

declare(strict_types=1);

namespace App\Modules\Metering\Http\Controllers\Web;

use App\Modules\Asset\Models\Asset;
use App\Modules\Metering\Actions\ManageAssetMeter;
use App\Modules\Metering\Actions\RecordMeterReading;
use App\Modules\Metering\Models\AssetMeter;
use App\Modules\Metering\Models\MeterReading;
use App\Modules\Metering\Models\MeterType;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Support\TenantTimezone;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Meters and their readings (SRS 11, ADR-013).
 *
 * The half of usage-based maintenance that was missing: a plan can say
 * "service every 500 running hours", but until somebody records the hours it
 * can never come due. Readings are entered against a machine, because that is
 * where the person standing with a clipboard is.
 *
 * Recording a reading answers immediately with what it brought due. Finding
 * out overnight is finding out too late for the shift that is running now.
 */
class MeterController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly TenantTimezone $timezone,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('meter.reading.view_any');

        $meters = AssetMeter::query()
            ->with(['asset:id,asset_code,name,current_factory_id', 'type:id,name,unit,is_cumulative'])
            ->whereHas('asset', fn ($q) => $q->whereIn('current_factory_id', $this->context->accessibleFactoryIds()))
            ->when($request->query('asset_id'), fn ($q, $id) => $q->where('asset_id', $id))
            ->when($request->query('status', 'ACTIVE'), fn ($q, $status) => $q->where('status', $status))
            ->orderBy('last_reading_at')
            ->paginate(min(max((int) $request->query('per_page', 25), 10), 100))
            ->withQueryString();

        return view('metering::meters.index', [
            'meters' => $meters,
            // Oldest reading first: a meter nobody has touched for weeks is
            // the one making a usage-based plan quietly wrong.
            'stale' => CarbonImmutable::now()->subDays(14),
        ]);
    }

    public function show(Request $request, AssetMeter $meter): View
    {
        $this->authorize('meter.reading.view_any');
        $this->assertReachable($meter);

        return view('metering::meters.show', [
            'meter' => $meter->load(['asset:id,asset_code,name', 'type']),
            'readings' => MeterReading::where('meter_id', $meter->id)
                ->orderByDesc('reading_at')
                ->limit(100)
                ->get(),
            'now' => $this->timezone->forInput(CarbonImmutable::now()),
        ]);
    }

    public function attach(Request $request, Asset $asset, ManageAssetMeter $action): RedirectResponse
    {
        $this->authorize('meter.meter.manage');

        $data = $request->validate([
            'meter_type_id' => ['required', 'string', 'size:26'],
            'initial_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $action->attach($asset, $data['meter_type_id'], $data['initial_value'] ?? '0');

        return back()->with('status', __('metering.meter_fitted'));
    }

    public function record(Request $request, AssetMeter $meter, RecordMeterReading $action): RedirectResponse
    {
        $this->authorize('meter.reading.create');
        $this->assertReachable($meter);

        $data = $request->validate([
            'value' => ['required', 'numeric', 'min:0'],
            'reading_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $action->handle(
            $meter,
            $data['value'],
            // Typed on the factory's clock, stored as the instant it names.
            filled($data['reading_at'] ?? null)
                ? $this->timezone->toUtc($data['reading_at'])
                : null,
            'MANUAL',
            $request->user()->id,
            $data['notes'] ?? null,
        );

        $triggered = $result['triggered']->count();

        return back()->with('status', $triggered > 0
            // Said now rather than discovered overnight: the person holding the
            // clipboard is the one who can act on it.
            ? trans_choice('metering.reading_triggered', $triggered, ['count' => $triggered])
            : __('metering.reading_recorded'));
    }

    /**
     * A meter replacement: the one legitimate way a cumulative reading drops.
     */
    public function reset(Request $request, AssetMeter $meter, RecordMeterReading $action): RedirectResponse
    {
        $this->authorize('meter.meter.reset');
        $this->assertReachable($meter);

        $data = $request->validate([
            'new_value' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $action->reset($meter, $data['new_value'], $data['reason'], $request->user()->id);

        return back()->with('status', __('metering.meter_reset'));
    }

    public function toggle(Request $request, AssetMeter $meter, ManageAssetMeter $action): RedirectResponse
    {
        $this->authorize('meter.meter.manage');
        $this->assertReachable($meter);

        $action->setStatus($meter, $meter->status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE');

        return back()->with('status', __('metering.meter_updated'));
    }

    /**
     * @return list<MeterType>
     */
    public static function typesFor(string $companyId): array
    {
        return MeterType::availableTo($companyId)
            ->where('active', true)
            ->orderBy('name')
            ->get()
            ->all();
    }

    private function assertReachable(AssetMeter $meter): void
    {
        $factoryId = $meter->asset?->current_factory_id;

        if ($factoryId === null || ! $this->context->canAccessFactory((string) $factoryId)) {
            abort(404);
        }
    }
}
