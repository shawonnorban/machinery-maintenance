<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Api;

use App\Modules\Api\Models\ApiToken;
use App\Modules\Asset\Models\Asset;
use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Billing\Models\SubscriptionContract;
use App\Modules\Billing\Models\UsageMetric;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Platform\Actions\OnboardTenant;
use App\Modules\Platform\Models\SupportGrant;
use App\Modules\Platform\Services\PlatformNotifier;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Scopes\TenantScope;
use App\Shared\Support\Sql;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The customers, seen from the platform (Platform API §2; mirrors the web
 * `TenantController`, which this delegates every write to unchanged per
 * ADR-003).
 *
 * Every query reads without the tenant scope, which in any other controller
 * would be a defect and is the whole point of this one. What this
 * deliberately never returns is any of a customer's actual operational
 * data — counts and contracts only (SRS 5.4).
 */
class PlatformTenantApiController extends ApiController
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly PlatformNotifier $notifier,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');

        if ($status === 'closed') {
            $closed = Company::withoutGlobalScope(TenantScope::class)
                ->onlyTrashed()
                ->orderByDesc('deleted_at')
                ->get();

            return ApiResponse::ok($closed->map(fn (Company $c): array => $this->closedSummary($c))->all());
        }

        $companies = Company::withoutGlobalScope(TenantScope::class)->orderBy('name')->get();

        $factoryCounts = Factory::withoutGlobalScope(TenantScope::class)
            ->groupBy('company_id')->selectRaw('company_id, count(*) as total')->pluck('total', 'company_id');
        $assetCounts = Asset::withoutGlobalScope(TenantScope::class)
            ->groupBy('company_id')->selectRaw('company_id, count(*) as total')->pluck('total', 'company_id');
        $userCounts = CompanyUser::withoutGlobalScope(TenantScope::class)
            ->where('status', 'ACTIVE')
            ->groupBy('company_id')->selectRaw('company_id, count(*) as total')->pluck('total', 'company_id');
        $contracts = SubscriptionContract::withoutGlobalScope(TenantScope::class)
            ->orderByDesc('start_date')->get()->groupBy('company_id');

        $rows = $companies
            ->when($status !== null, fn ($c) => $c->filter(fn (Company $company): bool => $status === 'suspended'
                ? $company->isSuspended()
                : $company->status === strtoupper((string) $status)))
            ->map(fn (Company $company): array => [
                'id' => $company->id,
                'name' => $company->name,
                'code' => $company->code,
                'status' => $company->status,
                'factories' => (int) ($factoryCounts[$company->id] ?? 0),
                'assets' => (int) ($assetCounts[$company->id] ?? 0),
                'users' => (int) ($userCounts[$company->id] ?? 0),
                'contract' => $this->contractSummary(($contracts[$company->id] ?? collect())->first()),
                'created_at' => $company->created_at?->toIso8601String(),
            ])
            ->values();

        return ApiResponse::ok($rows->all());
    }

    public function store(Request $request, OnboardTenant $action): JsonResponse
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

        // Returned once, here, and nowhere else — the API equivalent of the
        // web flow's one-time flash. There is no endpoint that will show
        // this password again.
        return ApiResponse::created([
            'company' => $this->detail($result['company']),
            'owner' => ['id' => $result['owner']->id, 'name' => $result['owner']->name, 'email' => $result['owner']->email],
            'factory' => ['id' => $result['factory']->id, 'name' => $result['factory']->name, 'code' => $result['factory']->code],
            'password' => $result['password'],
        ]);
    }

    public function show(Company $company): JsonResponse
    {
        $this->assertPlatformView($company);

        return ApiResponse::ok($this->detail($company));
    }

    /**
     * Only the people who could be acted as — for choosing who a support
     * grant steps in as, or whose sign-in gets rescued — never their data
     * (mirrors `TenantController::members()`/`owner()`).
     */
    public function members(Company $company): JsonResponse
    {
        $this->assertPlatformView($company);

        $ids = CompanyUser::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $company->id)
            ->where('status', 'ACTIVE')
            ->pluck('user_id');

        $owner = $this->owner($company);

        $members = User::whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_owner' => $owner !== null && $owner->id === $user->id,
            ]);

        return ApiResponse::ok($members->all());
    }

    /**
     * Twelve months of real, already-recorded usage (`UsageMeter` measures
     * every company monthly, billing:advance) — the analytics tab's charts,
     * one series per metric it actually writes today.
     *
     * @return array<string, mixed>
     */
    public function usage(Company $company): JsonResponse
    {
        $this->assertPlatformView($company);

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

        return ApiResponse::ok($byMetric);
    }

    public function update(Request $request, Company $company): JsonResponse
    {
        $this->assertPlatformView($company);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'country' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'base_currency' => ['required', 'string', 'size:3'],
            'timezone' => ['required', 'string', 'max:64'],
            'default_locale' => ['required', 'in:en,bn'],
        ]);

        $company->fill($data)->save();

        $this->audit->event(
            'UPDATED',
            ['reason' => 'TENANT_DETAILS_EDITED', 'company_id' => $company->id] + $data,
            userId: $request->user()->id,
            label: 'TENANT_DETAILS_EDITED',
        );

        return ApiResponse::ok($this->detail($company->fresh()));
    }

    public function suspend(Request $request, Company $company): JsonResponse
    {
        $this->assertPlatformView($company);

        if ($company->isSuspended()) {
            throw ApiException::of(ErrorCode::CONFLICT, __('platform.already_suspended'));
        }

        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]);

        $company->forceFill([
            'status' => 'SUSPENDED',
            'suspension_reason' => trim($data['reason']),
            'suspended_at' => now(),
            'suspended_by' => $request->user()->id,
        ])->save();

        DB::table('sessions')->whereIn('user_id', $this->memberIds($company))->delete();
        $this->revokeMemberTokens($company);

        $this->audit->event(
            'SECURITY_EVENT',
            ['reason' => 'TENANT_SUSPENDED', 'company_id' => $company->id, 'stated_reason' => $company->suspension_reason],
            userId: $request->user()->id,
            label: 'TENANT_SUSPENDED',
        );

        $this->notifier->notify(
            'PLATFORM_TENANT_SUSPENDED',
            __('platform.notify_suspended', ['name' => $request->user()->name, 'company' => $company->name]),
            $company->suspension_reason,
            'WARNING',
            exceptUserId: $request->user()->id,
        );

        return ApiResponse::ok($this->detail($company->fresh()));
    }

    public function reactivate(Request $request, Company $company): JsonResponse
    {
        $this->assertPlatformView($company);

        if (! $company->isSuspended()) {
            throw ApiException::of(ErrorCode::CONFLICT, __('platform.not_suspended'));
        }

        $company->forceFill([
            'status' => 'ACTIVE',
            'suspension_reason' => null,
            'suspended_at' => null,
            'suspended_by' => null,
        ])->save();

        $this->audit->event(
            'SECURITY_EVENT',
            ['reason' => 'TENANT_REACTIVATED', 'company_id' => $company->id],
            userId: $request->user()->id,
            label: 'TENANT_REACTIVATED',
        );

        return ApiResponse::ok($this->detail($company->fresh()));
    }

    public function destroy(Request $request, Company $company): JsonResponse
    {
        $this->assertPlatformView($company);

        $data = $request->validate([
            'confirm_code' => ['required', 'string'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        if (trim($data['confirm_code']) !== $company->code) {
            throw ApiException::of(ErrorCode::VALIDATION_ERROR, __('platform.confirm_code_mismatch', ['code' => $company->code]), [
                'confirm_code' => [__('platform.confirm_code_mismatch', ['code' => $company->code])],
            ]);
        }

        $memberIds = $this->memberIds($company);

        $this->audit->event(
            'DELETED',
            ['reason' => 'TENANT_CLOSED', 'company_id' => $company->id, 'company_code' => $company->code, 'stated_reason' => trim($data['reason'])],
            userId: $request->user()->id,
            label: $company->name,
        );

        DB::transaction(function () use ($company, $memberIds): void {
            DB::table('sessions')->whereIn('user_id', $memberIds)->delete();
            $company->delete();
        });

        $this->notifier->notify(
            'PLATFORM_TENANT_CLOSED',
            __('platform.notify_closed', ['name' => $request->user()->name, 'company' => $company->name]),
            trim($data['reason']),
            'WARNING',
            exceptUserId: $request->user()->id,
        );

        return ApiResponse::noContent();
    }

    public function restore(Request $request, string $company): JsonResponse
    {
        $target = $this->trashedTenant($company);

        $target->restore();

        $this->audit->event(
            'RESTORED',
            ['reason' => 'TENANT_REOPENED', 'company_id' => $target->id, 'company_code' => $target->code],
            userId: $request->user()->id,
            label: $target->name,
        );

        return ApiResponse::ok($this->detail($target->fresh()));
    }

    public function purge(Request $request, string $company): JsonResponse
    {
        $target = $this->trashedTenant($company);

        $data = $request->validate([
            'confirm_code' => ['required', 'string'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        if (trim($data['confirm_code']) !== $target->code) {
            throw ApiException::of(ErrorCode::VALIDATION_ERROR, __('platform.confirm_code_mismatch', ['code' => $target->code]), [
                'confirm_code' => [__('platform.confirm_code_mismatch', ['code' => $target->code])],
            ]);
        }

        $memberIds = $this->memberIds($target);

        $this->audit->event(
            'DELETED',
            [
                'reason' => 'TENANT_ERASED', 'company_id' => $target->id,
                'company_code' => $target->code, 'company_name' => $target->name,
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

        $this->notifier->notify(
            'PLATFORM_TENANT_ERASED',
            __('platform.notify_erased', ['name' => $request->user()->name, 'company' => $name]),
            trim($data['reason']),
            'CRITICAL',
            exceptUserId: $request->user()->id,
        );

        return ApiResponse::noContent();
    }

    private function eraseTenantData(string $companyId): void
    {
        $tables = collect(Schema::getTables())
            ->pluck('name')
            ->filter(fn (string $table): bool => collect(Schema::getColumns($table))
                ->contains(fn (array $column): bool => $column['name'] === 'company_id'))
            ->reject(fn (string $table): bool => $table === 'audit_logs')
            ->values();

        Sql::withoutForeignKeyChecks(
            [...$tables, 'audit_logs', 'companies'],
            function () use ($tables, $companyId): void {
                DB::table('audit_logs')->where('company_id', $companyId)->update(['company_id' => null]);

                foreach ($tables as $table) {
                    DB::table($table)->where('company_id', $companyId)->delete();
                }

                DB::table('companies')->where('id', $companyId)->delete();
            },
        );
    }

    private function trashedTenant(string $id): Company
    {
        $company = Company::withoutGlobalScope(TenantScope::class)->withTrashed()->findOrFail($id);

        if (! $company->trashed()) {
            abort(404);
        }

        return $company;
    }

    private function assertPlatformView(Company $company): void
    {
        if ($company->trashed()) {
            abort(404);
        }
    }

    /**
     * The company's owner: the one account that is the customer's own way
     * in. The first one, where there are several — a company that has
     * appointed a second owner already has somebody who can rescue the
     * first, and the platform only ever needs to rescue the account nobody
     * else can.
     */
    private function owner(Company $company): ?User
    {
        $roleId = Role::whereNull('company_id')->where('name', 'COMPANY_OWNER')->value('id');

        $userId = UserRole::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $company->id)
            ->where('role_id', $roleId)
            ->orderBy('created_at')
            ->value('user_id');

        return $userId === null ? null : User::find($userId);
    }

    /**
     * @return list<string>
     */
    private function memberIds(Company $company): array
    {
        return CompanyUser::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $company->id)
            ->pluck('user_id')
            ->all();
    }

    /**
     * A suspended or closed account's live tokens go with its sessions —
     * the API surface a stopped customer could otherwise keep using with a
     * bearer token nobody thought to revoke.
     */
    private function revokeMemberTokens(Company $company): void
    {
        PersonalAccessToken::where('company_id', $company->id)->delete();

        ApiToken::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $company->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function closedSummary(Company $company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'code' => $company->code,
            'closed_at' => $company->deleted_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function contractSummary(?SubscriptionContract $contract): ?array
    {
        if ($contract === null) {
            return null;
        }

        return [
            'id' => $contract->id,
            'contract_number' => $contract->contract_number,
            'status' => $contract->status,
            'billing_cycle' => $contract->billing_cycle,
            'amount' => $contract->amount,
            'currency' => $contract->currency,
            'start_date' => $contract->start_date?->toDateString(),
            'end_date' => $contract->end_date?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Company $company): array
    {
        $contract = SubscriptionContract::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $company->id)
            ->orderByDesc('start_date')
            ->first();

        return [
            'id' => $company->id,
            'name' => $company->name,
            'code' => $company->code,
            'legal_name' => $company->legal_name,
            'email' => $company->email,
            'phone' => $company->phone,
            'country' => $company->country,
            'address' => $company->address,
            'base_currency' => $company->base_currency,
            'timezone' => $company->timezone,
            'default_locale' => $company->default_locale,
            'status' => $company->status,
            'suspension_reason' => $company->suspension_reason,
            'suspended_at' => $company->suspended_at?->toIso8601String(),
            'logo_url' => $company->logoUrl(),
            'contract' => $this->contractSummary($contract),
            'factory_count' => Factory::withoutGlobalScope(TenantScope::class)->where('company_id', $company->id)->count(),
            'asset_count' => Asset::withoutGlobalScope(TenantScope::class)->where('company_id', $company->id)->count(),
            'user_count' => CompanyUser::withoutGlobalScope(TenantScope::class)
                ->where('company_id', $company->id)->where('status', 'ACTIVE')->count(),
            'open_support_grants' => SupportGrant::where('company_id', $company->id)
                ->whereNull('ended_at')->where('expires_at', '>', now())->count(),
            'created_at' => $company->created_at?->toIso8601String(),
        ];
    }
}
