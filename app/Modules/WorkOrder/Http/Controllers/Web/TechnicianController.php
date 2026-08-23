<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Http\Controllers\Web;

use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Department;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\ProductionLine;
use App\Modules\WorkOrder\Actions\ManageTechnician;
use App\Modules\WorkOrder\Models\Technician;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The maintenance roster (SRS 25).
 *
 * Who is on the floor and what they look after. There is no money on any of
 * these screens: technicians are salaried employees, so the record answers
 * "who do I send to the dye house" rather than "what does an hour cost".
 */
class TechnicianController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): View
    {
        $this->authorizeRoster($request);

        $technicians = Technician::query()
            ->with(['factory:id,name', 'department:id,name', 'productionLine:id,name'])
            ->whereIn('factory_id', $this->context->accessibleFactoryIds())
            ->when($request->query('department_id'), fn ($q, $id) => $q->where('department_id', $id))
            ->when($request->string('search')->trim()->toString(), function ($q, string $term): void {
                $q->where(fn ($w) => $w->where('name', 'like', $term.'%')
                    ->orWhere('employee_id', 'like', $term.'%'));
            })
            ->orderBy('name')
            ->paginate(min(max((int) $request->query('per_page', 25), 10), 100))
            ->withQueryString();

        return view('work_order::technicians.index', [
            'technicians' => $technicians,
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeRoster($request);

        return view('work_order::technicians.form', $this->formOptions() + ['technician' => null]);
    }

    public function store(Request $request, ManageTechnician $action): RedirectResponse
    {
        $this->authorizeRoster($request);

        $technician = $action->create($this->validated($request, null));

        return redirect()
            ->route('app.technicians.index')
            ->with('status', __('technician.created', ['name' => $technician->name]));
    }

    public function edit(Request $request, Technician $technician): View
    {
        $this->authorizeRoster($request);
        $this->assertReachable($technician);

        return view('work_order::technicians.form', $this->formOptions() + ['technician' => $technician]);
    }

    public function update(Request $request, Technician $technician, ManageTechnician $action): RedirectResponse
    {
        $this->authorizeRoster($request);
        $this->assertReachable($technician);

        $action->update($technician, $this->validated($request, $technician));

        return redirect()
            ->route('app.technicians.index')
            ->with('status', __('technician.updated', ['name' => $technician->name]));
    }

    public function toggle(Request $request, Technician $technician, ManageTechnician $action): RedirectResponse
    {
        $this->authorizeRoster($request);
        $this->assertReachable($technician);

        $action->setStatus($technician, $technician->isActive() ? 'INACTIVE' : 'ACTIVE');

        return back()->with('status', __('technician.updated', ['name' => $technician->name]));
    }

    public function destroy(Request $request, Technician $technician, ManageTechnician $action): RedirectResponse
    {
        $this->authorizeRoster($request);
        $this->assertReachable($technician);

        $name = $technician->name;

        $action->delete($technician);

        return redirect()
            ->route('app.technicians.index')
            ->with('status', __('technician.deleted', ['name' => $name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Technician $technician): array
    {
        $unique = Rule::unique('technicians', 'employee_id')
            ->where('company_id', $this->context->companyId());

        if ($technician !== null) {
            $unique = $unique->ignore($technician->id);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'employee_id' => ['required', 'string', 'max:64', $unique],
            'factory_id' => ['required', 'string', 'size:26'],
            'department_id' => ['nullable', 'string', 'size:26'],
            'production_line_id' => ['nullable', 'string', 'size:26'],
            'user_id' => ['nullable', 'string', 'size:26'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'specialization' => ['nullable', 'string', 'max:255'],
            'joining_date' => ['nullable', 'date'],
            'max_concurrent_work_orders' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        $companyId = $this->context->companyId();

        return [
            'factories' => Factory::whereIn('id', $this->context->accessibleFactoryIds())->orderBy('name')->get(),
            'departments' => Department::orderBy('name')->get(),
            'lines' => ProductionLine::orderBy('name')->get(),
            // Only members of this company, and only those not already linked
            // to somebody else on the roster.
            'users' => User::query()
                ->whereHas('memberships', fn ($q) => $q->where('company_id', $companyId)->where('status', 'ACTIVE'))
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ];
    }

    private function assertReachable(Technician $technician): void
    {
        if (! $this->context->canAccessFactory((string) $technician->factory_id)) {
            abort(403);
        }
    }

    private function authorizeRoster(Request $request): void
    {
        if (! $request->user()->can('technician.technician.manage')) {
            abort(403);
        }
    }
}
