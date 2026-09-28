<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Http\Controllers\Web;

use App\Modules\Maintenance\Actions\CompleteSchedule;
use App\Modules\Maintenance\Models\MaintenanceSchedule;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): View
    {
        $this->authorize('maintenance.schedule.view_any');

        $status = $request->query('status');

        $schedules = MaintenanceSchedule::query()
            ->with(['asset:id,asset_code,name,current_factory_id', 'plan:id,name,priority'])
            ->when($this->context->factoryScopeId(), function ($q, $factoryId): void {
                $q->whereHas('asset', fn ($a) => $a->where('current_factory_id', $factoryId));
            })
            ->when($status === 'OVERDUE', function ($q): void {
                // Overdue means past the grace period, not merely past the due
                // date (SRS 31.1). Reporting a plan late on day one of a
                // two-day grace would make compliance figures meaningless.
                $q->whereIn('status', MaintenanceSchedule::OPEN_STATUSES)
                    ->where(fn ($w) => $w->whereNotNull('grace_until')->where('grace_until', '<', now()));
            })
            ->when($status !== null && $status !== 'OVERDUE', fn ($q) => $q->where('status', $status))
            ->when($status === null, fn ($q) => $q->whereIn('status', MaintenanceSchedule::OPEN_STATUSES))
            ->orderBy('due_at')
            ->paginate(50)
            ->withQueryString();

        return view('maintenance::schedules.index', [
            'schedules' => $schedules,
            'status' => $status,
            'counts' => $this->counts(),
        ]);
    }

    public function complete(MaintenanceSchedule $schedule, CompleteSchedule $action): RedirectResponse
    {
        $this->authorize('maintenance.schedule.view_any');

        $action->handle($schedule, CarbonImmutable::now(), request()->user()->id);

        return back()->with('status', __('maintenance.schedule_completed'));
    }

    public function skip(MaintenanceSchedule $schedule, CompleteSchedule $action): RedirectResponse
    {
        $this->authorize('maintenance.schedule.skip');

        $validated = request()->validate([
            // A skip is a compliance exception, so it is never anonymous.
            'skipped_reason' => ['required', 'string', 'max:255'],
        ]);

        $action->skip($schedule, $validated['skipped_reason'], request()->user()->id);

        return back()->with('status', __('maintenance.schedule_skipped'));
    }

    public function reschedule(MaintenanceSchedule $schedule, CompleteSchedule $action): RedirectResponse
    {
        $this->authorize('maintenance.schedule.reschedule');

        $validated = request()->validate([
            'due_at' => ['required', 'date'],
            'rescheduled_reason' => ['required', 'string', 'max:255'],
        ]);

        $action->reschedule(
            $schedule,
            CarbonImmutable::parse($validated['due_at']),
            $validated['rescheduled_reason'],
            request()->user()->id,
        );

        return back()->with('status', __('maintenance.schedule_rescheduled'));
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        return [
            'due' => MaintenanceSchedule::whereIn('status', ['DUE'])->count(),
            'overdue' => MaintenanceSchedule::whereIn('status', MaintenanceSchedule::OPEN_STATUSES)
                ->whereNotNull('grace_until')->where('grace_until', '<', now())->count(),
            'planned' => MaintenanceSchedule::where('status', 'PLANNED')->count(),
        ];
    }
}
