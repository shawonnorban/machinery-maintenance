<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Web;

use App\Modules\Asset\Models\Asset;
use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Billing\Models\SubscriptionContract;
use App\Modules\Billing\Models\SubscriptionInvoice;
use App\Modules\Billing\Models\UsageMetric;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Platform\Actions\ManageSupportAccess;
use App\Modules\Platform\Actions\OnboardTenant;
use App\Modules\Platform\Models\SupportGrant;
use App\Modules\Platform\Models\SupportTicket;
use App\Modules\Platform\Services\PlatformNotifier;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\CompanyDomain;
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
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly PlatformNotifier $notifier,
    ) {}

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
            // Closed accounts, kept off the main grid and folded away below it.
            // They are not customers any more, but they are recoverable for as
            // long as they are here, and a list that simply forgot them would
            // make the mistake unrecoverable in practice.
            'closed' => Company::withoutGlobalScope(TenantScope::class)
                ->onlyTrashed()
                ->orderByDesc('deleted_at')
                ->get(),
            'openGrants' => SupportGrant::whereNull('ended_at')
                ->where('expires_at', '>', now())
                ->with('holder:id,name')
                ->get()
                ->groupBy('company_id'),

            // Real onboarding counts, not a placeholder trend. Six months is
            // enough to see whether the business is growing without dragging
            // in every customer since launch.
            'monthlyGrowth' => $this->monthlyGrowth(),
        ]);
    }

    /**
     * New customers per month, the six months up to and including this one.
     *
     * @return array<string, int>
     */
    private function monthlyGrowth(): array
    {
        $since = now()->subMonths(5)->startOfMonth();

        $counted = Company::withoutGlobalScope(TenantScope::class)
            ->where('created_at', '>=', $since)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, count(*) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $months = [];

        for ($i = 5; $i >= 0; $i--) {
            $cursor = now()->subMonths($i);
            $months[$cursor->format('M')] = (int) ($counted[$cursor->format('Y-m')] ?? 0);
        }

        return $months;
    }

    public function create(): View
    {
        return view('platform::tenants.create');
    }

    /**
     * The customer's details, as a form.
     *
     * Its own page rather than the Company tab itself. That tab used to *be*
     * this form, which meant looking up a customer's phone number showed it
     * sitting in an input box — every fact dressed as something you were
     * halfway through changing. Reading is the common errand and editing the
     * rare one, so reading is the page and this is a button on it.
     */
    public function edit(Company $company): View
    {
        $this->assertPlatformView($company);

        return view('platform::tenants.edit', ['company' => $company]);
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

    /**
     * One customer, one tab at a time.
     *
     * Company management, billing, tickets and the rest used to be panels
     * stacked on a single page, and the page grew a panel every time this
     * customer gained a capability until it was longer than anybody read.
     * Every tab still shares this one method and one data-loading pass —
     * the queries are counts and small collections, cheap enough that
     * fetching them for a tab that will not render them costs nothing worth
     * guarding against — while genuinely heavier, tab-specific data (the
     * ticket list, the usage history) is loaded only for the tab that shows
     * it.
     */
    public function show(Company $company, ?string $tab = null): View
    {
        $this->assertPlatformView($company);

        $tab ??= 'company';

        $data = [
            'company' => $company,
            'tab' => $tab,
            'contracts' => SubscriptionContract::withoutGlobalScope(TenantScope::class)
                ->where('company_id', $company->id)
                ->orderByDesc('start_date')
                ->get(),
            'invoices' => SubscriptionInvoice::withoutGlobalScope(TenantScope::class)
                ->where('company_id', $company->id)
                ->orderByDesc('issue_date')
                ->orderByDesc('invoice_number')
                ->limit(24)
                ->get(),
            'grants' => SupportGrant::where('company_id', $company->id)
                ->with(['holder:id,name', 'company:id,name'])
                ->orderByDesc('starts_at')
                ->limit(25)
                ->get(),
            // Only the people who could be acted as. Not their data.
            'members' => $this->members($company),

            // The account that *is* the customer, as far as the platform is
            // concerned: whoever holds Company Owner. Editing a sign-in is a
            // heavy enough act that offering it against every member of the
            // company turns it into a list to browse; there is one account a
            // platform operator ever needs to rescue, and this is it.
            'owner' => $owner = $this->owner($company),

            // The owner's *membership* of this company — when they joined it
            // and whether it is active — as distinct from their account, which
            // the same person can carry into a second company in a group with
            // its own separate tenure and its own separate suspension.
            'ownerMembership' => $owner === null ? null : CompanyUser::withoutGlobalScope(TenantScope::class)
                ->where('company_id', $company->id)
                ->where('user_id', $owner->id)
                ->first(),

            'factories' => $factories = Factory::withoutGlobalScope(TenantScope::class)
                ->where('company_id', $company->id)
                ->orderBy('name')
                ->get(),

            // Counts, so the contract's limits can be set against what the
            // customer is actually using rather than blind. Counts only — how
            // many machines is a size, which machines is their data.
            'factoryCount' => $factories->count(),
            'assetCount' => Asset::withoutGlobalScope(TenantScope::class)
                ->where('company_id', $company->id)
                ->count(),
            'userCount' => CompanyUser::withoutGlobalScope(TenantScope::class)
                ->where('company_id', $company->id)
                ->where('status', 'ACTIVE')
                ->count(),

            // The addresses this customer's system answers on. Primary first,
            // then the ones still waiting on the customer's DNS.
            'domains' => CompanyDomain::withoutGlobalScope(TenantScope::class)
                ->where('company_id', $company->id)
                ->orderByDesc('is_primary')
                ->orderBy('host')
                ->get(),

            // Open first, so the tab badge and the page agree on what counts
            // as needing attention.
            'openTicketCount' => SupportTicket::withoutGlobalScope(TenantScope::class)
                ->where('company_id', $company->id)
                ->whereIn('status', SupportTicket::OPEN_STATUSES)
                ->count(),
        ];

        if ($tab === 'tickets') {
            $data['tickets'] = SupportTicket::withoutGlobalScope(TenantScope::class)
                ->where('company_id', $company->id)
                ->with(['opener:id,name', 'assignee:id,name'])
                ->orderByRaw("status = 'CLOSED'")
                ->orderByDesc('last_message_at')
                ->get();
        }

        if ($tab === 'analytics') {
            $data['usageHistory'] = $this->usageHistory($company);
        }

        return view('platform::tenants.show', $data);
    }

    /**
     * Real, already-recorded usage: UsageMeter measures every company monthly
     * (billing:advance), and this reads that history back rather than
     * computing a fresh trend just for this page. Twelve months, grouped by
     * metric so the view can draw one chart per metric without reshaping
     * anything.
     *
     * Only the four metrics UsageMeter actually writes today. STORAGE_BYTES,
     * API_CALLS and WEBHOOK_DELIVERIES are named in UsageMetric::METRICS for
     * the schema they will eventually use, but nothing populates them yet —
     * three permanently empty charts would read as a fault on this page
     * rather than as three features not built.
     *
     * @return array<string, array<string, int>>
     */
    private function usageHistory(Company $company): array
    {
        $metrics = ['ACTIVE_FACTORIES', 'ACTIVE_ASSETS', 'ACTIVE_USERS', 'WORK_ORDERS_CREATED'];
        $since = now()->subMonths(11)->startOfMonth();

        $rows = UsageMetric::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $company->id)
            ->whereNull('factory_id')
            ->whereIn('metric', $metrics)
            ->where('period_start', '>=', $since)
            ->orderBy('period_start')
            ->get();

        $byMetric = array_fill_keys($metrics, []);

        foreach ($rows as $row) {
            $byMetric[$row->metric][$row->period_start->format('M Y')] = (int) $row->value;
        }

        return $byMetric;
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

        $suspending = ! $company->isSuspended();

        if ($suspending) {
            // Required, and long enough to be a sentence. The customer is shown
            // this verbatim on the screen that tells them they are stopped, and
            // "policy" answers nothing for somebody whose factory has just lost
            // its maintenance system.
            $data = $request->validate([
                'reason' => ['required', 'string', 'min:10', 'max:500'],
            ]);

            $company->forceFill([
                'status' => 'SUSPENDED',
                'suspension_reason' => trim($data['reason']),
                'suspended_at' => now(),
                'suspended_by' => $request->user()->id,
            ])->save();

            // Their sessions, not their data. Deleting these stops the people
            // already inside; the middleware is what stops them coming back.
            DB::table('sessions')
                ->whereIn('user_id', $this->members($company)->pluck('id'))
                ->delete();
        } else {
            $company->forceFill([
                'status' => 'ACTIVE',
                // Cleared rather than kept: a stale reason on a running company
                // would be read as a current one. The audit row is where the
                // history lives.
                'suspension_reason' => null,
                'suspended_at' => null,
                'suspended_by' => null,
            ])->save();
        }

        $this->audit->event(
            'SECURITY_EVENT',
            [
                'reason' => $suspending ? 'TENANT_SUSPENDED' : 'TENANT_REACTIVATED',
                'company_id' => $company->id,
                'stated_reason' => $suspending ? $company->suspension_reason : null,
            ],
            userId: $request->user()->id,
            label: $suspending ? 'TENANT_SUSPENDED' : 'TENANT_REACTIVATED',
        );

        if ($suspending) {
            // Only one way round. A customer being stopped is news; a customer
            // going back to working is the absence of news.
            $this->notifier->notify(
                'PLATFORM_TENANT_SUSPENDED',
                __('platform.notify_suspended', [
                    'name' => $request->user()->name,
                    'company' => $company->name,
                ]),
                $company->suspension_reason,
                'WARNING',
                route('platform.tenants.show', $company),
                exceptUserId: $request->user()->id,
            );
        }

        return back()->with('status', $suspending
            ? __('platform.suspended', ['name' => $company->name])
            : __('platform.reactivated', ['name' => $company->name]));
    }

    /**
     * Change what a customer is allowed, without replacing their contract.
     *
     * These are the numbers somebody actually adjusts: a customer buys twenty
     * more machines in March, and until now the only way to record that was to
     * supersede the whole contract with a new contract number — which put a
     * document in their file that nobody had signed, for a change of one field.
     *
     * The price, the term and the cycle are still contract terms and still
     * need a new contract. An entitlement is not.
     */
    public function updateLimits(Request $request, Company $company): RedirectResponse
    {
        $this->assertPlatformView($company);

        $contract = SubscriptionContract::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $company->id)
            ->orderByDesc('start_date')
            ->first();

        // The limits live on the contract, so there has to be one. Saying so is
        // better than silently writing them nowhere.
        if ($contract === null) {
            return back()->withErrors(['limits' => __('platform.limits_need_contract')]);
        }

        $data = $request->validate([
            'included_factories' => ['nullable', 'integer', 'min:0'],
            'included_assets' => ['nullable', 'integer', 'min:0'],
            'included_users' => ['nullable', 'integer', 'min:0'],
            'overage_policy' => ['required', 'in:'.implode(',', SubscriptionContract::OVERAGE_POLICIES)],
        ]);

        $before = $contract->only([
            'included_factories', 'included_assets', 'included_users', 'overage_policy',
        ]);

        $contract->forceFill($data)->save();

        $this->audit->event(
            'SUBSCRIPTION_CHANGED',
            [
                'reason' => 'LIMITS_CHANGED',
                'company_id' => $company->id,
                'contract_number' => $contract->contract_number,
                'from' => $before,
                'to' => $data,
            ],
            userId: $request->user()->id,
            label: $company->name,
        );

        return back()->with('status', __('platform.limits_saved'));
    }

    /**
     * Close a customer's account.
     *
     * Company soft-deletes, so this ends the account without destroying
     * anything: the customer leaves the list, nobody in it can sign in, and
     * every row is still on disk if it turns out to have been a mistake.
     * Erasing for real is a second, separate decision — see purge().
     */
    public function destroy(Request $request, Company $company): RedirectResponse
    {
        $this->assertPlatformView($company);

        $data = $request->validate([
            'confirm_code' => ['required', 'string'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        // Typed, not clicked. A confirm() dialog is dismissed by reflex the
        // third time somebody sees it; a code has to be read off the screen
        // and copied, which is the pause this decision is worth.
        if (trim($data['confirm_code']) !== $company->code) {
            return back()->withInput()->withErrors([
                'confirm_code' => __('platform.confirm_code_mismatch', ['code' => $company->code]),
            ]);
        }

        $memberIds = $this->members($company)->pluck('id');

        $this->audit->event(
            'DELETED',
            [
                'reason' => 'TENANT_CLOSED',
                'company_id' => $company->id,
                'company_code' => $company->code,
                'stated_reason' => trim($data['reason']),
            ],
            userId: $request->user()->id,
            label: $company->name,
        );

        DB::transaction(function () use ($company, $memberIds): void {
            DB::table('sessions')->whereIn('user_id', $memberIds)->delete();
            $company->delete();
        });

        $this->notifier->notify(
            'PLATFORM_TENANT_CLOSED',
            __('platform.notify_closed', [
                'name' => $request->user()->name,
                'company' => $company->name,
            ]),
            trim($data['reason']),
            'WARNING',
            exceptUserId: $request->user()->id,
        );

        return redirect()->route('platform.tenants')
            ->with('status', __('platform.closed_done', ['name' => $company->name]));
    }

    /**
     * Reopen a closed account. Nothing to put back — the rows never left.
     */
    public function restore(Request $request, string $company): RedirectResponse
    {
        $target = $this->trashedTenant($company);

        $target->restore();

        $this->audit->event(
            'RESTORED',
            ['reason' => 'TENANT_REOPENED', 'company_id' => $target->id, 'company_code' => $target->code],
            userId: $request->user()->id,
            label: $target->name,
        );

        return redirect()->route('platform.tenants.show', $target)
            ->with('status', __('platform.reopened_done', ['name' => $target->name]));
    }

    /**
     * Erase a closed account and everything in it, permanently.
     *
     * Reachable only from an account that is already closed. Two decisions on
     * two days is the design: nobody should be able to get from a working
     * customer to an empty database in one screen.
     *
     * There is no export yet (SRS §49), so this is the one operation in the
     * product with nothing behind it. The screen says so.
     */
    public function purge(Request $request, string $company): RedirectResponse
    {
        $target = $this->trashedTenant($company);

        $data = $request->validate([
            'confirm_code' => ['required', 'string'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        if (trim($data['confirm_code']) !== $target->code) {
            return back()->withInput()->withErrors([
                'purge_code' => __('platform.confirm_code_mismatch', ['code' => $target->code]),
            ]);
        }

        $memberIds = $this->members($target)->pluck('id');

        // Written before the delete, and carrying the name and code in the row
        // itself. audit_logs.company_id is nullOnDelete, so a row that only
        // pointed at the company would be left saying nothing at all — which
        // is the opposite of what an audit trail is for.
        $this->audit->event(
            'DELETED',
            [
                'reason' => 'TENANT_ERASED',
                'company_id' => $target->id,
                'company_code' => $target->code,
                'company_name' => $target->name,
                'stated_reason' => trim($data['reason']),
            ],
            userId: $request->user()->id,
            label: $target->name,
        );

        $name = $target->name;

        DB::transaction(function () use ($target, $memberIds): void {
            DB::table('sessions')->whereIn('user_id', $memberIds)->delete();

            $this->eraseTenantData($target->id);
        });

        // The people are left alone on purpose. A user who worked only here now
        // has no membership, which denyNoMembership already answers properly;
        // deleting them would break every audit row and created_by that names
        // them, and those rows are the reason to keep an audit trail at all.
        // The loudest one there is. Nothing else in this product destroys a
        // customer's data, and everybody who could have stopped it should find
        // out the same afternoon rather than the next time somebody looks.
        $this->notifier->notify(
            'PLATFORM_TENANT_ERASED',
            __('platform.notify_erased', [
                'name' => $request->user()->name,
                'company' => $name,
            ]),
            trim($data['reason']),
            'CRITICAL',
            exceptUserId: $request->user()->id,
        );

        return redirect()->route('platform.tenants')
            ->with('status', __('platform.erased_done', ['name' => $name]));
    }

    /**
     * Remove every row belonging to one customer.
     *
     * Deleting the company and letting the foreign keys cascade does not work
     * here, and the reason is worth writing down. Ninety-four company_id keys
     * cascade, but eight — the location hierarchy, from factories down to
     * workstations — are RESTRICT, because in normal use you must not delete a
     * factory that still has machines in it. That is the right rule for a
     * factory and the wrong one for an account being erased, and it stops the
     * delete dead. Nor can the eight simply be reordered around: factories
     * alone has thirteen RESTRICT children, and MySQL checks those before it
     * performs a cascade that would have removed them anyway.
     *
     * So the deletion is explicit and order-free instead: every table that has
     * a company_id, with the constraints stood down for the duration. The
     * table list is read from the schema rather than written out here, so a
     * module added next year is included without anybody remembering to.
     *
     * Nothing is orphaned by the missing checks: every table that points at a
     * tenant row is itself company-scoped and goes in the same pass.
     */
    private function eraseTenantData(string $companyId): void
    {
        $tables = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('COLUMN_NAME', 'company_id')
            ->pluck('TABLE_NAME')
            // The one deliberate survivor. Its key is nullOnDelete so that what
            // was done, by whom and when outlives the data it describes.
            ->reject(fn (string $table): bool => $table === 'audit_logs');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            DB::table('audit_logs')->where('company_id', $companyId)->update(['company_id' => null]);

            foreach ($tables as $table) {
                DB::table($table)->where('company_id', $companyId)->delete();
            }

            DB::table('companies')->where('id', $companyId)->delete();
        } finally {
            // In a finally because leaving a connection with its constraints
            // switched off is worse than the failure that got us here.
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * Closed accounts are excluded by the soft-delete scope and by
     * assertPlatformView, so the two screens that act on one resolve it here.
     */
    private function trashedTenant(string $id): Company
    {
        $company = Company::withoutGlobalScope(TenantScope::class)
            ->withTrashed()
            ->findOrFail($id);

        if (! $company->trashed()) {
            abort(404);
        }

        return $company;
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

        // The customer is told by the SUPPORT_ACCESS notice; this is the other
        // half of the same accountability, told to the colleagues who can act
        // on it without going and looking at an audit log.
        $this->notifier->notify(
            'PLATFORM_SUPPORT_OPENED',
            __('platform.notify_support_opened', [
                'name' => $request->user()->name,
                'company' => $company->name,
            ]),
            $data['reason'],
            'WARNING',
            route('platform.tenants.show', $company),
            exceptUserId: $request->user()->id,
        );

        return back()->with('status', __('platform.support_opened'));
    }

    public function closeSupport(Request $request, SupportGrant $grant, ManageSupportAccess $support): RedirectResponse
    {
        if ($grant->granted_to !== $request->user()->id) {
            abort(404);
        }

        $support->close($grant, $request->user());

        $this->notifier->notify(
            'PLATFORM_SUPPORT_CLOSED',
            __('platform.notify_support_closed', [
                'name' => $request->user()->name,
                'company' => $grant->company?->name ?? '—',
            ]),
            severity: 'INFO',
            exceptUserId: $request->user()->id,
        );

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
     * The company's owner: the one account that is the customer's own way in.
     *
     * The first one, where there are several — a company that has appointed a
     * second owner already has somebody who can rescue the first, and the
     * platform only ever needs to rescue the account nobody else can.
     */
    private function owner(Company $company): ?User
    {
        $roleId = Role::withoutGlobalScope(TenantScope::class)
            ->whereNull('company_id')
            ->where('code', 'COMPANY_OWNER')
            ->value('id');

        $userId = UserRole::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $company->id)
            ->where('role_id', $roleId)
            ->orderBy('created_at')
            ->value('user_id');

        return $userId === null ? null : User::find($userId);
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
