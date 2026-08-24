<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Web;

use App\Modules\Asset\Models\Asset;
use App\Modules\Billing\Models\SubscriptionContract;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Actions\ManageSupportAccess;
use App\Modules\Platform\Actions\OnboardTenant;
use App\Modules\Platform\Models\SupportGrant;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Scopes\TenantScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The customers, seen from the platform (SRS 3.1, 5, 40).
 *
 * Everything here queries without the tenant scope, which in any other
 * controller would be a defect. It is the whole point of this one: these are
 * the screens that exist above the tenancy rather than inside it.
 *
 * What they deliberately do not show is any of the customer's actual data. No
 * machines, no work orders, no breakdowns — counts and contracts only. SRS 5
 * says platform staff have no tenant data access by default, and a "helpful"
 * asset list here would be exactly the silent access SRS 5.4 prohibits.
 */
class TenantController extends Controller
{
    public function index(Request $request): View
    {
        $companies = Company::withoutGlobalScope(TenantScope::class)
            ->orderBy('name')
            ->get();

        $factoryCounts = Factory::withoutGlobalScope(TenantScope::class)
            ->groupBy('company_id')
            ->selectRaw('company_id, count(*) as total')
            ->pluck('total', 'company_id');

        $assetCounts = Asset::withoutGlobalScope(TenantScope::class)
            ->groupBy('company_id')
            ->selectRaw('company_id, count(*) as total')
            ->pluck('total', 'company_id');

        $userCounts = CompanyUser::withoutGlobalScope(TenantScope::class)
            ->where('status', 'ACTIVE')
            ->groupBy('company_id')
            ->selectRaw('company_id, count(*) as total')
            ->pluck('total', 'company_id');

        $contracts = SubscriptionContract::withoutGlobalScope(TenantScope::class)
            ->orderByDesc('start_date')
            ->get()
            ->groupBy('company_id');

        return view('platform::tenants.index', [
            'tenants' => $companies->map(fn (Company $company): array => [
                'company' => $company,
                'factories' => (int) ($factoryCounts[$company->id] ?? 0),
                'assets' => (int) ($assetCounts[$company->id] ?? 0),
                'users' => (int) ($userCounts[$company->id] ?? 0),
                'contract' => ($contracts[$company->id] ?? collect())->first(),
            ])->all(),
            'openGrants' => SupportGrant::whereNull('ended_at')
                ->where('expires_at', '>', now())
                ->with('holder:id,name')
                ->get()
                ->groupBy('company_id'),
        ]);
    }

    public function create(): View
    {
        return view('platform::tenants.create');
    }

    public function store(Request $request, OnboardTenant $action): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'base_currency' => ['required', 'string', 'size:3'],
            'timezone' => ['required', 'string', 'max:64'],
            'default_locale' => ['required', 'in:en,bn'],
            'factory_name' => ['required', 'string', 'max:255'],
            'factory_code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255'],
        ]);

        $result = $action->handle($data, $request->user()->id);

        // Flashed, not stored. Shown once to whoever is onboarding, for them to
        // pass on; there is no way back to it afterwards.
        return redirect()
            ->route('platform.tenants.show', $result['company'])
            ->with('status', __('platform.tenant_created', ['name' => $result['company']->name]))
            ->with('owner_email', $result['owner']->email)
            ->with('owner_password', $result['password']);
    }

    public function show(Company $company): View
    {
        $this->assertPlatformView($company);

        return view('platform::tenants.show', [
            'company' => $company,
            'contracts' => SubscriptionContract::withoutGlobalScope(TenantScope::class)
                ->where('company_id', $company->id)
                ->orderByDesc('start_date')
                ->get(),
            'grants' => SupportGrant::where('company_id', $company->id)
                ->with(['holder:id,name', 'company:id,name'])
                ->orderByDesc('starts_at')
                ->limit(25)
                ->get(),
            // Only the people who could be acted as. Not their data.
            'members' => $this->members($company),
            'factories' => Factory::withoutGlobalScope(TenantScope::class)
                ->where('company_id', $company->id)
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Stop a company using the product, without touching a row of its data.
     *
     * Suspension is a billing state, not a deletion. SRS 40: "Subscription
     * cancellation does not immediately delete data" — a customer who settles
     * an invoice on Friday must find everything where they left it on Monday.
     */
    public function suspend(Request $request, Company $company): RedirectResponse
    {
        $this->assertPlatformView($company);

        $suspending = $company->status === 'ACTIVE';

        $company->forceFill(['status' => $suspending ? 'SUSPENDED' : 'ACTIVE'])->save();

        if ($suspending) {
            // Their sessions, not their data. Somebody already signed in would
            // otherwise keep working until their session happened to expire.
            DB::table('sessions')
                ->whereIn('user_id', $this->members($company)->pluck('id'))
                ->delete();
        }

        return back()->with('status', $suspending
            ? __('platform.suspended', ['name' => $company->name])
            : __('platform.reactivated', ['name' => $company->name]));
    }

    /**
     * The customer's contract (SRS 40).
     *
     * "No mandatory fixed packages": every term is set per customer, so this
     * is a form rather than a plan picker. A plan catalogue would be the one
     * thing the specification explicitly refuses.
     *
     * A new contract supersedes rather than edits. An invoice already raised
     * under the old terms is a document somebody has been sent, and editing
     * the terms it was calculated from makes it unexplainable.
     */
    public function storeContract(Request $request, Company $company): RedirectResponse
    {
        $this->assertPlatformView($company);

        $data = $request->validate([
            'contract_number' => ['required', 'string', 'max:32'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'billing_cycle' => ['required', 'in:'.implode(',', SubscriptionContract::BILLING_CYCLES)],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'trial_end' => ['nullable', 'date'],
            'grace_period_days' => ['required', 'integer', 'min:0', 'max:180'],
            'auto_renew' => ['sometimes', 'boolean'],
            'included_factories' => ['nullable', 'integer', 'min:0'],
            'included_assets' => ['nullable', 'integer', 'min:0'],
            'included_users' => ['nullable', 'integer', 'min:0'],
            'overage_policy' => ['required', 'in:'.implode(',', SubscriptionContract::OVERAGE_POLICIES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($company, $data, $request): void {
            SubscriptionContract::withoutGlobalScope(TenantScope::class)
                ->where('company_id', $company->id)
                ->whereIn('status', ['ACTIVE', 'TRIAL', 'READ_ONLY'])
                ->update(['status' => 'ARCHIVED', 'archived_at' => now()]);

            SubscriptionContract::withoutGlobalScope(TenantScope::class)->create($data + [
                'company_id' => $company->id,
                'status' => ($data['trial_end'] ?? null) !== null ? 'TRIAL' : 'ACTIVE',
                'auto_renew' => $request->boolean('auto_renew'),
            ]);
        });

        return back()->with('status', __('platform.contract_saved'));
    }

    // -- Support access (SRS 5.4) -------------------------------------------

    public function openSupport(Request $request, Company $company, ManageSupportAccess $support): RedirectResponse
    {
        $this->assertPlatformView($company);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'hours' => ['required', 'integer', 'min:1', 'max:8'],
        ]);

        $support->open($company, $request->user(), $data['reason'], (int) $data['hours']);

        return back()->with('status', __('platform.support_opened'));
    }

    public function closeSupport(Request $request, SupportGrant $grant, ManageSupportAccess $support): RedirectResponse
    {
        if ($grant->granted_to !== $request->user()->id) {
            abort(404);
        }

        $support->close($grant, $request->user());

        return back()->with('status', __('platform.support_closed'));
    }

    /**
     * Step inside, as a named user of the company.
     *
     * From here on the platform administrator *is* that user: their
     * permissions, their factories, their screens. Nothing in the application
     * needs to know about support access, which is what makes this safe — there
     * is no parallel path with its own bugs, and no way to see more than the
     * customer's own account can see.
     */
    public function enterSupport(Request $request, SupportGrant $grant, ManageSupportAccess $support): RedirectResponse
    {
        if ($grant->granted_to !== $request->user()->id) {
            abort(404);
        }

        $data = $request->validate([
            'user_id' => ['required', 'string', 'size:26'],
        ]);

        $asUser = User::findOrFail($data['user_id']);
        $staff = $request->user();

        $support->enter($grant, $staff, $asUser);

        Auth::login($asUser);

        $request->session()->put(ManageSupportAccess::SESSION_KEY, $staff->id);
        $request->session()->put(ManageSupportAccess::GRANT_KEY, $grant->id);
        $request->session()->put('active_company_id', $grant->company_id);

        return redirect()->route('app.dashboard');
    }

    /**
     * @return Collection<int, User>
     */
    private function members(Company $company)
    {
        $ids = CompanyUser::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $company->id)
            ->where('status', 'ACTIVE')
            ->pluck('user_id');

        return User::whereIn('id', $ids)->orderBy('name')->get(['id', 'name', 'email']);
    }

    /**
     * Route model binding resolves a company without the tenant scope here,
     * because the platform area has no tenant. The check is that the record is
     * really a company and not soft-deleted — there is nothing narrower to
     * enforce, which is exactly why the audit trail matters instead.
     */
    private function assertPlatformView(Company $company): void
    {
        if ($company->trashed()) {
            abort(404);
        }
    }
}
