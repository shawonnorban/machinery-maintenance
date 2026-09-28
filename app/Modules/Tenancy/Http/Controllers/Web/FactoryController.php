<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers\Web;

use App\Modules\Tenancy\Actions\SaveFactory;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Factories (SRS 4).
 *
 * The first screen a new tenant needs and the last one that was built: until
 * now a company could only get a factory through the seeder or an import, and
 * without one it cannot register a single machine.
 */
class FactoryController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): View
    {
        $this->authorizeSettings($request);

        return view('tenancy::factories.index', [
            'factories' => Factory::withCount([
                'assets as asset_count',
            ])->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeSettings($request);

        return view('tenancy::factories.form', [
            'factory' => null,
            'timezones' => $this->timezones(),
        ]);
    }

    public function store(Request $request, SaveFactory $action): RedirectResponse
    {
        $this->authorizeSettings($request);

        $factory = $action->create($this->validated($request, null));

        return redirect()
            ->route('app.settings.factories')
            ->with('status', __('settings.factory_created', ['name' => $factory->name]));
    }

    public function edit(Request $request, Factory $factory): View
    {
        $this->authorizeSettings($request);

        return view('tenancy::factories.form', [
            'factory' => $factory,
            'timezones' => $this->timezones(),
        ]);
    }

    public function update(Request $request, Factory $factory, SaveFactory $action): RedirectResponse
    {
        $this->authorizeSettings($request);

        $action->update($factory, $this->validated($request, $factory));

        return redirect()
            ->route('app.settings.factories')
            ->with('status', __('settings.factory_updated', ['name' => $factory->name]));
    }

    public function toggle(Request $request, Factory $factory, SaveFactory $action): RedirectResponse
    {
        $this->authorizeSettings($request);

        $action->setStatus($factory, $factory->status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE');

        return back()->with('status', __('settings.factory_updated', ['name' => $factory->name]));
    }

    public function destroy(Request $request, Factory $factory, SaveFactory $action): RedirectResponse
    {
        $this->authorizeSettings($request);

        $name = $factory->name;

        $action->delete($factory);

        return redirect()
            ->route('app.settings.factories')
            ->with('status', __('settings.factory_deleted', ['name' => $name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Factory $factory): array
    {
        // The code is set once. It is printed on labels and embedded in work
        // order numbers, so changing it would strand everything already issued.
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'timezone' => ['required', 'string', Rule::in($this->timezones())],
        ];

        if ($factory === null) {
            $rules['code'] = [
                'required', 'string', 'max:5', 'regex:/^[A-Za-z0-9]+$/',
                Rule::unique('factories')->where('company_id', $this->context->companyId()),
            ];
        }

        return $request->validate($rules);
    }

    /**
     * @return list<string>
     */
    private function timezones(): array
    {
        // A short list rather than all 400: every one of these is a country a
        // Bangladeshi group actually operates or sources in, and a picker with
        // four hundred entries is one nobody sets correctly.
        return [
            'Asia/Dhaka', 'Asia/Kolkata', 'Asia/Karachi', 'Asia/Colombo',
            'Asia/Yangon', 'Asia/Bangkok', 'Asia/Ho_Chi_Minh', 'Asia/Jakarta',
            'Asia/Shanghai', 'Asia/Hong_Kong', 'Asia/Singapore', 'Asia/Dubai',
            'Europe/Istanbul', 'Europe/London', 'UTC',
        ];
    }

    private function authorizeSettings(Request $request): void
    {
        if (! $request->user()->can('settings.factory.manage')) {
            abort(403);
        }
    }
}
