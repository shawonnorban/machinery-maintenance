<?php

declare(strict_types=1);

namespace App\Modules\Calendar\Http\Controllers\Web;

use App\Modules\Calendar\Actions\ManageFactoryCalendar;
use App\Modules\Calendar\Models\FactoryCalendar;
use App\Modules\Calendar\Models\FactoryHoliday;
use App\Modules\Calendar\Models\Shift;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The working week, the shifts and the holidays (SRS 7).
 *
 * Availability divides downtime by scheduled operating time, and a maintenance
 * date that lands on a rest day is moved to the next working one. Both read
 * this, so a factory that cannot set its own week is reading somebody else's
 * arithmetic.
 */
class CalendarController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): View
    {
        $this->authorizeCalendar($request);

        $factory = $this->selectedFactory($request);

        return view('calendar::calendar.index', [
            'factories' => $this->factories(),
            'factory' => $factory,
            'calendar' => $factory === null ? null : FactoryCalendar::where('factory_id', $factory->id)
                ->whereNull('effective_to')
                ->orderByDesc('effective_from')
                ->first(),
            'history' => $factory === null ? collect() : FactoryCalendar::where('factory_id', $factory->id)
                ->orderByDesc('effective_from')
                ->get(),
            'shifts' => $factory === null ? collect() : Shift::where('factory_id', $factory->id)
                ->with('breaks')
                ->orderBy('start_time')
                ->get(),
            'holidays' => $factory === null ? collect() : FactoryHoliday::where('factory_id', $factory->id)
                ->whereDate('date', '>=', CarbonImmutable::now()->subMonths(3)->toDateString())
                ->orderBy('date')
                ->get(),
            'today' => CarbonImmutable::now()->toDateString(),
        ]);
    }

    public function storeCalendar(Request $request, ManageFactoryCalendar $action): RedirectResponse
    {
        $this->authorizeCalendar($request);

        $data = $request->validate([
            'factory_id' => ['required', 'string', 'size:26'],
            'operating_mode' => ['required', Rule::in(['SHIFT_BASED', 'CONTINUOUS'])],
            'weekly_off_days' => ['nullable', 'array'],
            'weekly_off_days.*' => ['integer', 'between:1,7'],
            'effective_from' => ['required', 'date'],
        ]);

        $action->setCalendar($data['factory_id'], $data);

        return redirect()
            ->route('app.settings.calendar', ['factory_id' => $data['factory_id']])
            ->with('status', __('calendar.calendar_saved'));
    }

    public function storeShift(Request $request, ManageFactoryCalendar $action): RedirectResponse
    {
        $this->authorizeCalendar($request);

        $data = $this->validatedShift($request, null);

        $action->saveShift($data['factory_id'], $data);

        return redirect()
            ->route('app.settings.calendar', ['factory_id' => $data['factory_id']])
            ->with('status', __('calendar.shift_saved'));
    }

    public function updateShift(Request $request, Shift $shift, ManageFactoryCalendar $action): RedirectResponse
    {
        $this->authorizeCalendar($request);
        $this->assertReachable($shift->factory_id);

        $data = $this->validatedShift($request, $shift);

        $action->saveShift($shift->factory_id, $data, $shift);

        return redirect()
            ->route('app.settings.calendar', ['factory_id' => $shift->factory_id])
            ->with('status', __('calendar.shift_saved'));
    }

    public function destroyShift(Request $request, Shift $shift, ManageFactoryCalendar $action): RedirectResponse
    {
        $this->authorizeCalendar($request);
        $this->assertReachable($shift->factory_id);

        $action->deleteShift($shift);

        return redirect()
            ->route('app.settings.calendar', ['factory_id' => $shift->factory_id])
            ->with('status', __('calendar.shift_ended'));
    }

    public function storeHoliday(Request $request, ManageFactoryCalendar $action): RedirectResponse
    {
        $this->authorizeCalendar($request);

        $data = $request->validate([
            'factory_id' => ['required', 'string', 'size:26'],
            'date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'is_working_day' => ['sometimes', 'boolean'],
        ]);

        $action->addHoliday($data['factory_id'], $data + [
            'is_working_day' => $request->boolean('is_working_day'),
        ]);

        return redirect()
            ->route('app.settings.calendar', ['factory_id' => $data['factory_id']])
            ->with('status', __('calendar.holiday_saved'));
    }

    public function destroyHoliday(Request $request, FactoryHoliday $holiday, ManageFactoryCalendar $action): RedirectResponse
    {
        $this->authorizeCalendar($request);
        $this->assertReachable($holiday->factory_id);

        $factoryId = $holiday->factory_id;

        $action->removeHoliday($holiday);

        return redirect()
            ->route('app.settings.calendar', ['factory_id' => $factoryId])
            ->with('status', __('calendar.holiday_removed'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedShift(Request $request, ?Shift $shift): array
    {
        $factoryId = $shift?->factory_id ?? $request->input('factory_id');

        $unique = Rule::unique('shifts', 'code')
            ->where(fn ($q) => $q->where('factory_id', $factoryId));

        if ($shift !== null) {
            $unique = $unique->ignore($shift->id);
        }

        return $request->validate([
            'factory_id' => ['required', 'string', 'size:26'],
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

    private function selectedFactory(Request $request): ?Factory
    {
        $factories = $this->factories();

        $requested = $request->query('factory_id');

        return $factories->firstWhere('id', $requested) ?? $factories->first();
    }

    private function factories()
    {
        return Factory::whereIn('id', $this->context->accessibleFactoryIds())
            ->orderBy('name')
            ->get();
    }

    private function assertReachable(?string $factoryId): void
    {
        if (! $this->context->canAccessFactory((string) $factoryId)) {
            abort(404);
        }
    }

    private function authorizeCalendar(Request $request): void
    {
        if (! $request->user()->can('settings.calendar.manage')) {
            abort(403);
        }
    }
}
