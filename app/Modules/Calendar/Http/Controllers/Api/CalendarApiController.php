<?php

declare(strict_types=1);

namespace App\Modules\Calendar\Http\Controllers\Api;

use App\Modules\Calendar\Actions\ManageFactoryCalendar;
use App\Modules\Calendar\Models\FactoryCalendar;
use App\Modules\Calendar\Models\FactoryHoliday;
use App\Modules\Calendar\Models\Shift;
use App\Modules\Calendar\Services\WorkingTimeService;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The working week, over the wire (API 5.1, Gap Analysis 3.4 #36; mirrors the
 * web `CalendarController`, which this delegates every write to unchanged
 * per ADR-003).
 *
 * There is one permission for the whole area, `settings.calendar.manage`,
 * used for reads as well as writes here exactly as the web screen does — a
 * factory's shift pattern is operational configuration, not something every
 * viewer role needs to see.
 */
class CalendarApiController extends ApiController
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly ManageFactoryCalendar $action,
    ) {}

    public function show(Factory $factory): JsonResponse
    {
        $this->allow('settings.calendar.manage');
        $this->assertReachable($factory->id);

        $calendar = FactoryCalendar::where('factory_id', $factory->id)
            ->whereNull('effective_to')
            ->orderByDesc('effective_from')
            ->first();

        $shifts = Shift::where('factory_id', $factory->id)
            ->where('status', 'ACTIVE')
            ->with('breaks')
            ->orderBy('start_time')
            ->get();

        return ApiResponse::ok([
            'factory_id' => $factory->id,
            'calendar' => $calendar === null ? null : $this->calendarSummary($calendar),
            'shifts' => $shifts->map(fn (Shift $shift): array => $this->shiftSummary($shift))->all(),
        ]);
    }

    public function update(Request $request, Factory $factory): JsonResponse
    {
        $this->allow('settings.calendar.manage');
        $this->assertReachable($factory->id);

        $data = $request->validate([
            'operating_mode' => ['required', Rule::in(['SHIFT_BASED', 'CONTINUOUS'])],
            'weekly_off_days' => ['nullable', 'array'],
            'weekly_off_days.*' => ['integer', 'between:1,7'],
            'effective_from' => ['required', 'date'],
        ]);

        // A new effective-dated version, not an edit in place — the same
        // reason a labour rate was never overwritten either.
        return ApiResponse::created($this->calendarSummary($this->action->setCalendar($factory->id, $data)));
    }

    public function shifts(Factory $factory): JsonResponse
    {
        $this->allow('settings.calendar.manage');
        $this->assertReachable($factory->id);

        $shifts = Shift::where('factory_id', $factory->id)
            ->with('breaks')
            ->orderBy('start_time')
            ->get();

        return ApiResponse::ok($shifts->map(fn (Shift $shift): array => $this->shiftSummary($shift))->all());
    }

    public function storeShift(Request $request, Factory $factory): JsonResponse
    {
        $this->allow('settings.calendar.manage');
        $this->assertReachable($factory->id);

        $data = $this->validatedShift($request, $factory->id, null);

        return ApiResponse::created($this->shiftSummary($this->action->saveShift($factory->id, $data)));
    }

    public function updateShift(Request $request, Shift $shift): JsonResponse
    {
        $this->allow('settings.calendar.manage');
        $this->assertReachable($shift->factory_id);

        $data = $this->validatedShift($request, $shift->factory_id, $shift);

        return ApiResponse::ok($this->shiftSummary($this->action->saveShift($shift->factory_id, $data, $shift)));
    }

    public function destroyShift(Shift $shift): JsonResponse
    {
        $this->allow('settings.calendar.manage');
        $this->assertReachable($shift->factory_id);

        $this->action->deleteShift($shift);

        return ApiResponse::noContent();
    }

    public function holidays(Factory $factory): JsonResponse
    {
        $this->allow('settings.calendar.manage');
        $this->assertReachable($factory->id);

        $holidays = FactoryHoliday::where('factory_id', $factory->id)
            ->whereDate('date', '>=', CarbonImmutable::now()->subMonths(3)->toDateString())
            ->orderBy('date')
            ->get();

        return ApiResponse::ok($holidays->map(fn (FactoryHoliday $h): array => $this->holidaySummary($h))->all());
    }

    public function storeHoliday(Request $request, Factory $factory): JsonResponse
    {
        $this->allow('settings.calendar.manage');
        $this->assertReachable($factory->id);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'is_working_day' => ['sometimes', 'boolean'],
        ]);

        return ApiResponse::created($this->holidaySummary($this->action->addHoliday($factory->id, $data)));
    }

    public function destroyHoliday(FactoryHoliday $holiday): JsonResponse
    {
        $this->allow('settings.calendar.manage');
        $this->assertReachable($holiday->factory_id);

        $this->action->removeHoliday($holiday);

        return ApiResponse::noContent();
    }

    /**
     * Scheduled operating minutes for a window (SRS 47.2, API 5.1) — the
     * exact number every availability figure in the product divides by,
     * exposed so a client can reproduce it rather than trust it blind.
     */
    public function workingTime(Request $request, Factory $factory, WorkingTimeService $service): JsonResponse
    {
        $this->allow('settings.calendar.manage');
        $this->assertReachable($factory->id);

        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after:from'],
        ]);

        $result = $service->scheduledOperatingMinutes(
            $factory,
            CarbonImmutable::parse($data['from']),
            CarbonImmutable::parse($data['to']),
        );

        return ApiResponse::ok($result);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedShift(Request $request, string $factoryId, ?Shift $shift): array
    {
        $unique = Rule::unique('shifts', 'code')->where(fn ($q) => $q->where('factory_id', $factoryId));

        if ($shift !== null) {
            $unique = $unique->ignore($shift->id);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32', $unique],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'days_of_week' => ['required', 'array', 'min:1'],
            'days_of_week.*' => ['integer', 'between:1,7'],
            'is_overtime' => ['sometimes', 'boolean'],
            'effective_from' => ['required', 'date'],
        ]) + ['is_overtime' => $request->boolean('is_overtime')];
    }

    /**
     * @return array<string, mixed>
     */
    private function calendarSummary(FactoryCalendar $calendar): array
    {
        return [
            'id' => $calendar->id,
            'factory_id' => $calendar->factory_id,
            'operating_mode' => $calendar->operating_mode,
            'weekly_off_days' => $calendar->weekly_off_days,
            'effective_from' => $calendar->effective_from->toDateString(),
            'effective_to' => $calendar->effective_to?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shiftSummary(Shift $shift): array
    {
        return [
            'id' => $shift->id,
            'factory_id' => $shift->factory_id,
            'name' => $shift->name,
            'code' => $shift->code,
            'start_time' => $shift->start_time,
            'end_time' => $shift->end_time,
            'crosses_midnight' => $shift->crosses_midnight,
            'days_of_week' => $shift->days_of_week,
            'is_overtime' => $shift->is_overtime,
            'status' => $shift->status,
            'effective_from' => $shift->effective_from->toDateString(),
            'effective_to' => $shift->effective_to?->toDateString(),
            'breaks' => $shift->relationLoaded('breaks') ? $shift->breaks->map(fn ($break): array => [
                'id' => $break->id,
                'start_time' => $break->start_time,
                'end_time' => $break->end_time,
                'counts_as_operating_time' => $break->counts_as_operating_time,
            ])->all() : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function holidaySummary(FactoryHoliday $holiday): array
    {
        return [
            'id' => $holiday->id,
            'factory_id' => $holiday->factory_id,
            'date' => $holiday->date->toDateString(),
            'name' => $holiday->name,
            'is_working_day' => $holiday->is_working_day,
        ];
    }

    private function assertReachable(string $factoryId): void
    {
        if (! $this->context->canAccessFactory($factoryId)) {
            abort(404);
        }
    }
}
