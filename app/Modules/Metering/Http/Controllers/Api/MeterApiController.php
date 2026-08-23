<?php

declare(strict_types=1);

namespace App\Modules\Metering\Http\Controllers\Api;

use App\Modules\Asset\Models\Asset;
use App\Modules\Metering\Actions\RecordMeterReading;
use App\Modules\Metering\Models\AssetMeter;
use App\Modules\Metering\Models\MeterReading;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Meters and their readings (API 10).
 *
 * This is the endpoint the whole idempotency mechanism was built for. A dye
 * house controller posting hours over a factory network will retry when a
 * response is slow, and a reading counted twice does not merely inflate a
 * number — it brings the next service forward and can trigger a maintenance
 * job that is not due.
 *
 * Readings are the one place a machine caller genuinely writes more than a
 * person does, so this is also where `source` earns its place: a reading that
 * arrived from a PLC and one somebody typed on a tablet are different kinds of
 * evidence, and the row says which.
 */
class MeterApiController extends ApiController
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * The meters on one machine.
     */
    public function index(Asset $asset): JsonResponse
    {
        $this->allow('meter.reading.view_any');
        $this->assertReachable($asset);

        $meters = AssetMeter::where('asset_id', $asset->id)
            ->with('type:id,name,unit,is_cumulative')
            ->orderBy('created_at')
            ->get();

        return ApiResponse::ok(
            $meters->map(fn (AssetMeter $meter): array => $this->meter($meter))->all(),
        );
    }

    /**
     * A meter's readings, newest first.
     *
     * Cursor-paginated: this is an append-only table that grows for ever on a
     * busy machine, and counting it on every page is the cost cursor
     * pagination exists to avoid (API 29).
     */
    public function readings(Request $request, AssetMeter $meter): JsonResponse
    {
        $this->allow('meter.reading.view_any');
        $this->assertMeterReachable($meter);

        $readings = MeterReading::where('meter_id', $meter->id)
            ->orderByDesc('reading_at')
            ->orderByDesc('id')
            ->cursorPaginate($this->perPage($request));

        return ApiResponse::cursor($readings, fn (MeterReading $reading): array => [
            'id' => $reading->id,
            'value' => $reading->value,
            'previous_value' => $reading->previous_value,
            'delta' => $reading->delta,
            'reading_at' => $reading->reading_at?->toIso8601String(),
            'source' => $reading->source,
            'source_reference' => $reading->source_reference,
            'is_reset_baseline' => (bool) $reading->is_reset_baseline,
            'notes' => $reading->notes,
        ]);
    }

    /**
     * Record a reading.
     *
     * The response says what the reading brought due, not merely that it was
     * accepted. A reading whose consequence is invisible looks like data entry,
     * and the caller cannot tell whether the integration is working.
     */
    public function store(Request $request, AssetMeter $meter, RecordMeterReading $action): JsonResponse
    {
        $this->allow('meter.reading.create');
        $this->assertMeterReachable($meter);

        $data = $request->validate([
            'value' => ['required', 'numeric'],
            'reading_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            // The caller's own identifier for this reading, kept so a person
            // reconciling two systems can match rows without guessing.
            'source_reference' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $action->handle(
            $meter,
            (string) $data['value'],
            isset($data['reading_at']) ? CarbonImmutable::parse($data['reading_at']) : null,
            // A machine caller is never 'MANUAL'. Somebody reading this row in
            // two years should be able to tell a PLC from a tablet.
            $this->caller()->isMachine() ? 'API' : 'MANUAL',
            $this->caller()->auditUserId(),
            $data['notes'] ?? null,
            $data['source_reference'] ?? null,
        );

        return ApiResponse::created([
            'id' => $result['reading']->id,
            'value' => $result['reading']->value,
            'delta' => $result['reading']->delta,
            'reading_at' => $result['reading']->reading_at?->toIso8601String(),
            'meter' => $this->meter($meter->fresh()),
            'triggered_maintenance' => count($result['triggered']),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function meter(AssetMeter $meter): array
    {
        return [
            'id' => $meter->id,
            'asset_id' => $meter->asset_id,
            'type' => [
                'id' => $meter->meter_type_id,
                'name' => $meter->type?->name,
                'unit' => $meter->type?->unit,
                'is_cumulative' => (bool) $meter->type?->is_cumulative,
            ],
            'current_value' => $meter->current_value,
            'last_reading_at' => $meter->last_reading_at?->toIso8601String(),
            'status' => $meter->status,
        ];
    }

    private function assertReachable(Asset $asset): void
    {
        if (! $this->context->canAccessFactory((string) $asset->current_factory_id)) {
            abort(404);
        }
    }

    private function assertMeterReachable(AssetMeter $meter): void
    {
        $asset = Asset::find($meter->asset_id);

        if ($asset === null) {
            abort(404);
        }

        $this->assertReachable($asset);
    }
}
