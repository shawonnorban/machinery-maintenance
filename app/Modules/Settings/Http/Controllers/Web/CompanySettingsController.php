<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Controllers\Web;

use App\Modules\Settings\Actions\SetSetting;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Models\SettingDefinition;
use App\Modules\Settings\Services\SettingsResolver;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * How this company wants the product to behave (SRS 20, ADR-054).
 *
 * Several of these change what a number means rather than how a screen looks:
 * whether planned downtime counts against availability, what a maintenance
 * date does when it lands on a rest day, whether stock may go negative. They
 * are audited for that reason.
 *
 * A setting can be answered once for the company and again for a single
 * factory, which is why each row shows where its current answer comes from.
 * A factory that has not answered inherits, and that is different from a
 * factory that answered the same way — the first follows the company when the
 * company changes its mind.
 */
class CompanySettingsController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly SettingsResolver $resolver,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeSettings($request);

        $factory = $this->selectedFactory($request);

        $definitions = SettingDefinition::orderBy('key')->get();

        return view('settings::company.index', [
            'company' => Company::findOrFail($this->context->companyId()),
            'factories' => $this->factories(),
            'factory' => $factory,
            'groups' => $definitions->groupBy(fn (SettingDefinition $d) => explode('.', $d->key)[0]),
            'rows' => $definitions->mapWithKeys(fn (SettingDefinition $definition) => [
                $definition->key => $this->rowFor($definition, $factory),
            ]),
        ]);
    }

    public function update(Request $request, SetSetting $action): RedirectResponse
    {
        $this->authorizeSettings($request);

        $data = $request->validate([
            'key' => ['required', 'string', 'max:128'],
            'value' => ['nullable'],
            'factory_id' => ['nullable', 'string', 'size:26'],
        ]);

        $factoryId = filled($data['factory_id'] ?? null) ? $data['factory_id'] : null;

        if ($factoryId !== null && ! $this->context->canAccessFactory($factoryId)) {
            abort(403);
        }

        $definition = $this->resolver->definition($data['key']);

        // An unchecked box sends nothing at all, which for a boolean means
        // false rather than "leave it alone".
        $value = $definition->value_type === 'BOOL'
            ? $request->boolean('value')
            : ($data['value'] ?? null);

        $action->handle($data['key'], $value, $factoryId, null, $request->user()->id);

        return redirect()
            ->route('app.settings.company', array_filter(['factory_id' => $factoryId]))
            ->with('status', __('settings.saved', ['name' => $definition->name]));
    }

    /**
     * Drop a factory's own answer so it follows the company again.
     */
    public function reset(Request $request): RedirectResponse
    {
        $this->authorizeSettings($request);

        $data = $request->validate([
            'key' => ['required', 'string', 'max:128'],
            'factory_id' => ['required', 'string', 'size:26'],
        ]);

        if (! $this->context->canAccessFactory($data['factory_id'])) {
            abort(403);
        }

        Setting::where('company_id', $this->context->companyId())
            ->where('factory_id', $data['factory_id'])
            ->whereNull('production_line_id')
            ->where('key', $data['key'])
            ->delete();

        $this->resolver->flush();

        return redirect()
            ->route('app.settings.company', ['factory_id' => $data['factory_id']])
            ->with('status', __('settings.reset_to_company'));
    }

    /**
     * The current answer and where it came from.
     *
     * @return array<string, mixed>
     */
    private function rowFor(SettingDefinition $definition, ?Factory $factory): array
    {
        $companyValue = Setting::where('company_id', $this->context->companyId())
            ->whereNull('factory_id')
            ->whereNull('production_line_id')
            ->where('key', $definition->key)
            ->value('value');

        $factoryValue = $factory === null ? null : Setting::where('company_id', $this->context->companyId())
            ->where('factory_id', $factory->id)
            ->whereNull('production_line_id')
            ->where('key', $definition->key)
            ->value('value');

        $hasFactoryAnswer = $factory !== null && $factoryValue !== null;
        $hasCompanyAnswer = $companyValue !== null;

        return [
            'definition' => $definition,
            'effective' => $factory === null
                ? $this->resolver->get($definition->key)
                : $this->resolver->get($definition->key, $factory->id),
            'source' => match (true) {
                $hasFactoryAnswer => 'FACTORY',
                $hasCompanyAnswer => 'COMPANY',
                default => 'PLATFORM',
            },
            'editable_here' => $factory === null
                ? $definition->allowsLevel('COMPANY')
                : $definition->allowsLevel('FACTORY'),
            'has_own_answer' => $hasFactoryAnswer,
        ];
    }

    private function selectedFactory(Request $request): ?Factory
    {
        $requested = $request->query('factory_id');

        if (! filled($requested)) {
            return null;
        }

        return $this->factories()->firstWhere('id', $requested);
    }

    private function factories()
    {
        return Factory::whereIn('id', $this->context->accessibleFactoryIds())
            ->orderBy('name')
            ->get();
    }

    private function authorizeSettings(Request $request): void
    {
        if (! $request->user()->can('settings.company.manage')) {
            abort(403);
        }
    }
}
